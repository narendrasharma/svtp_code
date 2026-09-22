<?php

namespace App\Services;

use App\Enums\HotelBookingStatus;
use App\Models\HotelBooking;
use App\Models\HotelReview;
use App\Models\Property;
use App\Models\User;
use App\Notifications\HotelReviewNotification;
use App\Support\HotelSettings;
use App\Support\ModuleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Hotel-only verified stay reviews (12B.8).
 *
 * Eligibility is the authenticated booking owner + Completed. CheckedOut is
 * deliberately ineligible: Hotel operations has a separate final Complete
 * transition. Payment collection is independent of operational completion;
 * cancelled/refunded cancellations and no-shows cannot reach Completed.
 *
 * All review writes go through this service. One booking lock + a UNIQUE
 * hotel_booking_id protect retries. Property-first review locks serialize
 * moderation/replies and approved-only aggregate refreshes. No delete API:
 * rejection withdraws publication while retaining the append-only audit.
 */
class HotelReviewService
{
    public function __construct(
        private HotelRatingSummaryService $summaries,
        private ActivityLogger $activity,
        private ModuleManager $modules,
    ) {}

    public function enabled(): bool
    {
        return $this->modules->isEnabled('hotels') && HotelSettings::enabled('hotel.reviews.enabled');
    }

    /** @return array{can_review: bool, has_review: bool, can_edit: bool, status: ?string, reason: ?string} */
    public function eligibility(HotelBooking $booking, User $customer): array
    {
        $denied = ['can_review' => false, 'has_review' => false, 'can_edit' => false, 'status' => null];

        if (! $this->enabled()) {
            return $denied + ['reason' => 'Hotel reviews are currently disabled.'];
        }

        if ($booking->user_id === null || (int) $booking->user_id !== (int) $customer->id) {
            return $denied + ['reason' => 'Only your own hotel bookings can be reviewed.'];
        }

        $review = $booking->review;

        if ($review) {
            return [
                'can_review' => false, 'has_review' => true,
                'can_edit' => $review->status === HotelReview::STATUS_PENDING,
                'status' => $review->status, 'reason' => 'You have already reviewed this stay.',
            ];
        }

        if ($booking->status !== HotelBookingStatus::Completed || $booking->cancelled_at !== null || $booking->property_id === null) {
            return $denied + ['reason' => 'Reviews open after your hotel stay is marked completed.'];
        }

        return ['can_review' => true, 'has_review' => false, 'can_edit' => false, 'status' => null, 'reason' => null];
    }

    /** @param array<string, mixed> $input */
    public function submit(HotelBooking $booking, User $customer, array $input): HotelReview
    {
        abort_unless($this->enabled(), 404);
        $this->assertCustomer($booking, $customer);
        $data = $this->validatedReview($input);
        $autoApprove = ! HotelSettings::enabled('hotel.reviews.moderation_enabled');

        try {
            return DB::transaction(function () use ($booking, $customer, $data, $autoApprove): HotelReview {
                $booking = HotelBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                $this->assertCustomer($booking, $customer);

                if ($booking->status !== HotelBookingStatus::Completed || $booking->cancelled_at !== null || $booking->property_id === null) {
                    throw ValidationException::withMessages(['booking' => 'Reviews open after your hotel stay is marked completed.']);
                }

                Property::withTrashed()->whereKey($booking->property_id)->lockForUpdate()->firstOrFail();

                if (HotelReview::where('hotel_booking_id', $booking->id)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['booking' => 'You have already reviewed this stay.']);
                }

                $review = new HotelReview;
                $review->forceFill([
                    ...$data, 'hotel_booking_id' => $booking->id,
                    'property_id' => $booking->property_id, 'user_id' => $customer->id,
                    'verified_stay' => true,
                    'status' => $autoApprove ? HotelReview::STATUS_APPROVED : HotelReview::STATUS_PENDING,
                    'published_at' => $autoApprove ? now() : null,
                ])->save();

                $this->activity->log('hotel_review.submitted', 'hotels', 'Verified stay review submitted.', $review, null, $data + ['status' => $review->status], $customer);

                if ($autoApprove) {
                    $this->summaries->refresh($review->property_id);
                    $this->notifyApproved($review);
                } else {
                    $this->notifyPending($review);
                }

                return $review;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (HotelReview::where('hotel_booking_id', $booking->id)->exists()) {
                throw ValidationException::withMessages(['booking' => 'You have already reviewed this stay.']);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function updatePending(HotelBooking $booking, User $customer, array $input): HotelReview
    {
        abort_unless($this->enabled(), 404);
        $this->assertCustomer($booking, $customer);
        $data = $this->validatedReview($input);
        $review = HotelReview::where('hotel_booking_id', $booking->id)->where('user_id', $customer->id)->firstOrFail();

        return DB::transaction(function () use ($review, $customer, $data): HotelReview {
            $review = $this->locked($review);
            abort_unless((int) $review->user_id === (int) $customer->id, 404);

            if ($review->status !== HotelReview::STATUS_PENDING) {
                throw ValidationException::withMessages(['review' => 'Only pending reviews can be edited.']);
            }

            $old = $review->only(array_keys($data));
            $review->fill($data);

            if ($review->isDirty()) {
                $review->save();
                $this->activity->log('hotel_review.edited', 'hotels', 'Pending review edited by its customer.', $review, $old, $data, $customer);
            }

            return $review;
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function moderate(HotelReview $review, User $actor, array $input): HotelReview
    {
        abort_unless($this->modules->isEnabled('hotels'), 404);
        abort_unless($actor->isAdmin() && $actor->can('hotel.reviews.moderate'), 403);
        $data = Validator::make([
            'action' => $input['action'] ?? null,
            'reason' => $this->plainText($input['reason'] ?? null),
        ], [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($review, $actor, $data): HotelReview {
            $review = $this->locked($review);
            $status = $data['action'] === 'approve' ? HotelReview::STATUS_APPROVED : HotelReview::STATUS_REJECTED;
            $old = $review->only(['status', 'rejection_reason']);
            $reason = $status === HotelReview::STATUS_REJECTED ? ($data['reason'] ?: null) : null;

            if ($review->status === $status && $review->rejection_reason === $reason) {
                return $review;
            }

            $review->forceFill([
                'status' => $status, 'rejection_reason' => $reason,
                'moderated_by' => $actor->id, 'moderated_at' => now(),
                'published_at' => $status === HotelReview::STATUS_APPROVED ? ($review->published_at ?? now()) : $review->published_at,
            ])->save();

            if ($old['status'] === HotelReview::STATUS_APPROVED || $status === HotelReview::STATUS_APPROVED) {
                $this->summaries->refresh($review->property_id);
            }

            $this->activity->log('hotel_review.'.$status, 'hotels', 'Hotel review '.$status.'.', $review, $old, $review->only(['status', 'rejection_reason']), $actor);

            if ($old['status'] !== $status) {
                if ($status === HotelReview::STATUS_APPROVED) {
                    $this->notifyApproved($review);
                } else {
                    $this->notifyCustomer($review, 'rejected');
                }
            }

            return $review;
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function reply(HotelReview $review, User $actor, array $input): HotelReview
    {
        abort_unless($this->enabled(), 404);
        $text = $this->validatedReply($input);

        return DB::transaction(function () use ($review, $actor, $text): HotelReview {
            $review = $this->locked($review);

            if ($actor->isVendor()) {
                $profile = $actor->vendorProfile;
                abort_unless($profile && $profile->is_active && (int) $review->property->vendor_profile_id === (int) $profile->id, 404);
            } else {
                abort_unless($actor->isAdmin() && $actor->can('hotel.reviews.reply'), 403);
                abort_if($review->vendor_reply !== null, 422, 'Use reply moderation to edit an existing response.');
            }

            if (! HotelSettings::enabled('hotel.reviews.vendor_replies_enabled')) {
                throw ValidationException::withMessages(['reply' => 'Property responses are currently disabled.']);
            }

            if ($review->status !== HotelReview::STATUS_APPROVED) {
                throw ValidationException::withMessages(['reply' => 'Only approved reviews can receive a response.']);
            }

            $firstReply = $review->vendor_reply === null;
            $this->saveReply($review, $actor, $text);

            if ($firstReply) {
                $this->notifyCustomer($review, 'replied');
            }

            return $review;
        }, 3);
    }

    /** @param array<string, mixed>|null $input Null removes the response, retaining its audit. */
    public function moderateReply(HotelReview $review, User $actor, ?array $input): HotelReview
    {
        abort_unless($this->modules->isEnabled('hotels'), 404);
        abort_unless($actor->isAdmin() && $actor->can('hotel.reviews.moderate'), 403);
        $text = $input === null ? null : $this->validatedReply($input);

        return DB::transaction(function () use ($review, $actor, $text): HotelReview {
            $review = $this->locked($review);

            if ($review->vendor_reply === null && $text !== null) {
                throw ValidationException::withMessages(['reply' => 'There is no property response to moderate.']);
            }

            $this->saveReply($review, $actor, $text);

            return $review;
        }, 3);
    }

    public function publicReviews(Property $property, string $sort = 'recent'): LengthAwarePaginator
    {
        $query = HotelReview::approved()->where('property_id', $property->id)
            ->with('user:id,name')
            ->select(['id', 'user_id', 'overall_rating', 'title', 'comment', 'status', 'verified_stay', 'published_at', 'vendor_reply', 'replied_at']);

        if (in_array($sort, ['highest', 'lowest'], true)) {
            $query->orderBy('overall_rating', $sort === 'highest' ? 'desc' : 'asc');
        }

        return $query->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(10, ['*'], 'reviews_page')->withQueryString()->fragment('reviews')
            ->through(fn (HotelReview $review): array => $review->publicPayload());
    }

    /** @param array<string, mixed> $filters */
    public function managementQuery(array $filters, ?int $vendorId = null): Builder
    {
        return HotelReview::query()->with(['user:id,name', 'property:id,name,slug,vendor_profile_id', 'property.vendorProfile:id,business_name'])
            ->when($vendorId !== null, fn (Builder $query) => $query->whereHas('property', fn (Builder $property) => $property->where('vendor_profile_id', $vendorId)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['property_id'] ?? null, fn (Builder $query, int $id) => $query->where('property_id', $id))
            ->when($filters['vendor_profile_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('property', fn (Builder $property) => $property->where('vendor_profile_id', $id)))
            ->when($filters['rating'] ?? null, fn (Builder $query, int $rating) => $query->where('overall_rating', $rating))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', $date.' 23:59:59'))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('title', 'like', '%'.$search.'%')->orWhere('comment', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%')));
            })
            ->latest('created_at')->latest('id');
    }

    /** @return array<string, array<int, mixed>> */
    public static function filterRules(): array
    {
        return [
            'status' => ['nullable', Rule::in(HotelReview::STATUSES)],
            'property_id' => ['nullable', 'integer', 'min:1'],
            'vendor_profile_id' => ['nullable', 'integer', 'min:1'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validatedReview(array $input): array
    {
        $rules = [];

        foreach (['overall_rating', ...array_keys(HotelReview::CATEGORY_RATINGS)] as $key) {
            $rules[$key] = ['required', 'integer', 'between:1,5'];
        }

        $rules['title'] = ['nullable', 'string', 'max:120'];
        $rules['comment'] = ['required', 'string', 'min:'.HotelSettings::minimumReviewLength(), 'max:2000', 'regex:/\p{L}/u'];
        $data = array_intersect_key($input, $rules);
        $data['title'] = $this->plainText($input['title'] ?? null);
        $data['comment'] = $this->plainText($input['comment'] ?? null);

        return Validator::make($data, $rules, [
            'comment.regex' => 'Please describe your stay using words.',
        ])->validate();
    }

    /** @param array<string, mixed> $input */
    private function validatedReply(array $input): string
    {
        return Validator::make(['reply' => $this->plainText($input['reply'] ?? null)], [
            'reply' => ['required', 'string', 'min:10', 'max:2000', 'regex:/\p{L}/u'],
        ])->validate()['reply'];
    }

    private function plainText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $text = preg_replace('/[\p{Z}\s]+/u', ' ', strip_tags($value));

        return trim(preg_replace('/\p{Cf}/u', '', $text ?? ''));
    }

    private function assertCustomer(HotelBooking $booking, User $customer): void
    {
        abort_unless($booking->user_id !== null && (int) $booking->user_id === (int) $customer->id, 404);
    }

    private function locked(HotelReview $review): HotelReview
    {
        $property = Property::withTrashed()->whereKey($review->property_id)->lockForUpdate()->firstOrFail();
        $review = HotelReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
        $review->setRelation('property', $property);

        return $review;
    }

    private function saveReply(HotelReview $review, User $actor, ?string $text): void
    {
        if ($review->vendor_reply === $text) {
            return;
        }

        $old = $review->only(['vendor_reply', 'replied_by', 'replied_at']);
        $event = $text === null ? 'reply_removed' : ($review->vendor_reply === null ? 'reply_added' : 'reply_updated');
        $review->forceFill(['vendor_reply' => $text, 'replied_by' => $text === null ? null : $actor->id, 'replied_at' => $text === null ? null : now()])->save();
        $this->activity->log('hotel_review.'.$event, 'hotels', 'Official property response '.str_replace('reply_', '', $event).'.', $review, $old, $review->only(['vendor_reply', 'replied_by', 'replied_at']), $actor);
    }

    private function notifyCustomer(HotelReview $review, string $kind): void
    {
        $review->user?->notify(new HotelReviewNotification($kind, $review->property->name,
            route('account.hotel-reviews.show', $review->hotel_booking_id, absolute: false)));
    }

    private function notifyApproved(HotelReview $review): void
    {
        $this->notifyCustomer($review, 'approved');
        $vendor = $review->property->vendorProfile;

        if ($vendor?->is_active) {
            $vendor->user?->notify(new HotelReviewNotification('received', $review->property->name,
                route('vendor.hotel.reviews.show', $review, absolute: false)));
        }
    }

    private function notifyPending(HotelReview $review): void
    {
        foreach (User::where('role', 'admin')->with(['roles.permissions', 'permissions'])->lazyById(50) as $admin) {
            if ($admin->can('hotel.reviews.view') && $admin->can('hotel.reviews.moderate')) {
                $admin->notify(new HotelReviewNotification('pending', $review->property->name,
                    route('admin.hotel.reviews.show', $review, absolute: false)));
            }
        }
    }
}
