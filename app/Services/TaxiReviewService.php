<?php

namespace App\Services;

use App\Enums\TaxiBookingStatus;
use App\Models\TaxiBooking;
use App\Models\TaxiReview;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\TaxiSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Post-trip taxi reviews (Phase 12A.12).
 *
 * Booking-backed and server-authoritative: relations (vendor, driver,
 * vehicle, customer) always derive from the completed booking + its
 * final assignment, never from request input. Eligibility checks are
 * read-only (no audit writes). Reviews never touch pricing snapshots,
 * earnings, payouts or dispatch ranking.
 */
class TaxiReviewService
{
    public const RATINGS = ['overall_rating', 'driver_rating', 'vehicle_rating', 'service_rating', 'punctuality_rating', 'cleanliness_rating'];

    public const OPTIONAL_RATINGS = ['driver_rating', 'vehicle_rating', 'service_rating', 'punctuality_rating', 'cleanliness_rating'];

    public function enabled(): bool
    {
        return TaxiSettings::enabled('taxi.reviews.enabled');
    }

    public function requireModeration(): bool
    {
        return TaxiSettings::enabled('taxi.reviews.require_moderation');
    }

    public function allowTextReview(): bool
    {
        return TaxiSettings::enabled('taxi.reviews.allow_text_review');
    }

    public function allowVendorReply(): bool
    {
        return TaxiSettings::enabled('taxi.reviews.allow_vendor_reply');
    }

    public function reviewWindowDays(): ?int
    {
        $raw = TaxiSettings::get('taxi.reviews.review_window_days');

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return max(1, (int) $raw);
    }

    public function lowRatingThreshold(): int
    {
        return min(5, max(1, (int) (TaxiSettings::get('taxi.reviews.low_rating_threshold') ?? 2)));
    }

    /**
     * Read-only eligibility check. Never writes, never audits.
     *
     * @return array{eligible: bool, reason: ?string}
     */
    public function eligibility(TaxiBooking $booking, User $customer): array
    {
        if (! $this->enabled()) {
            return ['eligible' => false, 'reason' => 'Taxi reviews are currently disabled.'];
        }

        if ($booking->customer_user_id === null || (int) $booking->customer_user_id !== (int) $customer->id) {
            return ['eligible' => false, 'reason' => 'This booking does not belong to your account.'];
        }

        if ($booking->status()->isTerminal() && $booking->status !== TaxiBookingStatus::Completed->value) {
            return ['eligible' => false, 'reason' => 'Only completed trips can be reviewed.'];
        }

        if ($booking->status !== TaxiBookingStatus::Completed->value) {
            return ['eligible' => false, 'reason' => 'This trip is not completed yet.'];
        }

        if (TaxiReview::where('taxi_booking_id', $booking->id)->exists()) {
            return ['eligible' => false, 'reason' => 'This trip has already been reviewed.'];
        }

        if ($this->windowExpired($booking)) {
            return ['eligible' => false, 'reason' => 'The review window for this trip has closed.'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    public function windowExpired(TaxiBooking $booking): bool
    {
        $days = $this->reviewWindowDays();

        if ($days === null) {
            return false;
        }

        $completedAt = $booking->completed_at ?? $booking->updated_at ?? now();

        return $completedAt->lt(now()->subDays($days));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function submit(TaxiBooking $booking, User $customer, array $input): TaxiReview
    {
        return DB::transaction(function () use ($booking, $customer, $input): TaxiReview {
            $booking = TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $this->enabled()) {
                throw ValidationException::withMessages(['review' => 'Taxi reviews are currently disabled.']);
            }

            $check = $this->eligibility($booking, $customer);

            if (! $check['eligible']) {
                throw ValidationException::withMessages(['booking' => $check['reason']]);
            }

            $ratings = $this->validatedRatings($input, true);
            $comment = $this->validatedComment($input['comment'] ?? null);

            // Final assignment wins: the driver/vehicle that actually
            // completed the trip, not an earlier reassigned pair.
            $assignment = $booking->assignments()->open()->latest('assigned_at')->first()
                ?? $booking->assignments()->latest('assigned_at')->first();

            $autoApprove = ! $this->requireModeration();

            try {
                $review = TaxiReview::create([
                    'taxi_booking_id' => $booking->id,
                    'customer_user_id' => $customer->id,
                    'vendor_profile_id' => $booking->vendor_profile_id,
                    'driver_id' => $assignment?->driver_id ?? $booking->assigned_driver_id,
                    'vehicle_id' => $assignment?->vehicle_id ?? $booking->assigned_vehicle_id,
                    ...$ratings,
                    'comment' => $comment,
                    'status' => $autoApprove ? TaxiReview::STATUS_APPROVED : TaxiReview::STATUS_PENDING,
                    'submitted_at' => now(),
                    'approved_at' => $autoApprove ? now() : null,
                ]);
            } catch (QueryException $e) {
                if (TaxiReview::where('taxi_booking_id', $booking->id)->exists()) {
                    throw ValidationException::withMessages(['booking' => 'This trip has already been reviewed.']);
                }

                throw $e;
            }

            $this->notifySubmitted($booking->fresh(), $review->fresh());

            return $review->fresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, int|null>
     */
    protected function validatedRatings(array $input, bool $requireOverall): array
    {
        $out = [];
        $overall = $input['overall_rating'] ?? null;

        if ($requireOverall && ($overall === null || filter_var($overall, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]) === false)) {
            throw ValidationException::withMessages(['overall_rating' => 'Overall rating must be a whole number from 1 to 5.']);
        }

        $out['overall_rating'] = (int) $overall;

        foreach (self::OPTIONAL_RATINGS as $key) {
            $value = $input[$key] ?? null;

            if ($value === null || $value === '') {
                $out[$key] = null;

                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]) === false) {
                throw ValidationException::withMessages([$key => 'Ratings must be whole numbers from 1 to 5.']);
            }

            $out[$key] = (int) $value;
        }

        return $out;
    }

    protected function validatedComment(mixed $comment): ?string
    {
        if ($comment === null || trim((string) $comment) === '') {
            return null;
        }

        if (! $this->allowTextReview()) {
            throw ValidationException::withMessages(['comment' => 'Text reviews are currently disabled; please submit ratings only.']);
        }

        // Plain text only: strip tags server-side; Blade/Vue escape on render.
        $text = trim(strip_tags((string) $comment));

        if (mb_strlen($text) > 1000) {
            throw ValidationException::withMessages(['comment' => 'Review text must be 1000 characters or fewer.']);
        }

        return $text === '' ? null : $text;
    }

    public function moderate(TaxiReview $review, string $action, ?User $actor, ?string $note = null): TaxiReview
    {
        return DB::transaction(function () use ($review, $action, $actor, $note): TaxiReview {
            $review = TaxiReview::whereKey($review->id)->lockForUpdate()->firstOrFail();

            $note = $note !== null && trim($note) !== '' ? mb_substr(trim(strip_tags($note)), 0, 500) : null;

            $review->forceFill(match ($action) {
                'approve' => [
                    'status' => TaxiReview::STATUS_APPROVED,
                    'approved_at' => $review->approved_at ?? now(),
                    'rejected_at' => null,
                    'moderated_by' => $actor?->id,
                    'moderation_note' => $note ?? $review->moderation_note,
                ],
                'reject' => [
                    'status' => TaxiReview::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'moderated_by' => $actor?->id,
                    'moderation_note' => $note ?? $review->moderation_note,
                ],
                'hide' => [
                    'status' => TaxiReview::STATUS_HIDDEN,
                    'moderated_by' => $actor?->id,
                    'moderation_note' => $note ?? $review->moderation_note,
                ],
                'unhide' => [
                    'status' => TaxiReview::STATUS_APPROVED,
                    'approved_at' => $review->approved_at ?? now(),
                    'moderated_by' => $actor?->id,
                    'moderation_note' => $note ?? $review->moderation_note,
                ],
                default => throw ValidationException::withMessages(['action' => 'Unknown moderation action.']),
            })->save();

            $fresh = $review->fresh();

            if (in_array($action, ['approve', 'unhide'], true)) {
                $this->notifyApproved($fresh);
            } elseif ($action === 'reject') {
                $this->notifyRejected($fresh);
            }

            return $fresh;
        }, 3);
    }

    public function reply(TaxiReview $review, User $vendorUser, string $text): TaxiReview
    {
        return DB::transaction(function () use ($review, $vendorUser, $text): TaxiReview {
            $review = TaxiReview::whereKey($review->id)->lockForUpdate()->firstOrFail();

            if (! $this->allowVendorReply()) {
                throw ValidationException::withMessages(['reply' => 'Vendor replies are currently disabled.']);
            }

            $profile = $vendorUser->vendorProfile;
            abort_unless($profile && $profile->is_active && $review->vendor_profile_id !== null
                && (int) $review->vendor_profile_id === (int) $profile->id, 404);
            abort_unless($review->status === TaxiReview::STATUS_APPROVED, 422, 'Only approved reviews can receive a reply.');

            $clean = trim(strip_tags($text));

            if ($clean === '' || mb_strlen($clean) > 1000) {
                throw ValidationException::withMessages(['reply' => 'Reply must be 1–1000 characters of plain text.']);
            }

            $review->forceFill(['vendor_reply' => $clean, 'vendor_replied_at' => now()])->save();

            return $review->fresh();
        }, 3);
    }

    public function flag(TaxiReview $review, User $actor, string $reason, ?string $note = null): TaxiReview
    {
        return DB::transaction(function () use ($review, $actor, $reason, $note): TaxiReview {
            $review = TaxiReview::whereKey($review->id)->lockForUpdate()->firstOrFail();

            if (! in_array($reason, TaxiReview::FLAG_REASONS, true)) {
                throw ValidationException::withMessages(['reason' => 'Unknown flag reason.']);
            }

            $review->forceFill([
                'flagged_at' => now(),
                'flagged_by' => $actor->id,
                'flag_reason' => $reason,
                'flag_note' => $note !== null && trim($note) !== '' ? mb_substr(trim(strip_tags($note)), 0, 500) : null,
            ])->save();

            try {
                foreach (User::where('role', 'admin')->limit(25)->get() as $admin) {
                    $admin->notify(new CrmNotification('taxi_review_flagged', [
                        'taxi_review_id' => $review->id,
                        'taxi_booking_id' => $review->taxi_booking_id,
                        'reason' => $reason,
                    ]));
                }
            } catch (\Throwable) {
                // Flagging must never fail because notifications do.
            }

            return $review->fresh();
        }, 3);
    }

    /**
     * Privacy-safe customer display name: first name + last initial.
     */
    public static function displayName(?User $customer): string
    {
        $name = trim((string) ($customer?->name ?? ''));

        if ($name === '') {
            return 'Verified traveller';
        }

        $parts = preg_split('/\s+/', $name);
        $first = $parts[0];

        if (count($parts) < 2) {
            return mb_substr($first, 0, 1).'.';
        }

        return $first.' '.mb_substr(end($parts), 0, 1).'.';
    }

    protected function notifySubmitted(TaxiBooking $booking, TaxiReview $review): void
    {
        try {
            $booking->customer?->notify(new CrmNotification('taxi_review_submitted', [
                'taxi_booking_id' => $booking->id,
                'reference' => $booking->reference,
                'taxi_review_id' => $review->id,
                'status' => $review->status,
            ]));

            $booking->vendorProfile?->user?->notify(new CrmNotification('taxi_review_received', [
                'taxi_booking_id' => $booking->id,
                'reference' => $booking->reference,
                'taxi_review_id' => $review->id,
                'overall_rating' => $review->overall_rating,
            ]));

            if ($review->overall_rating <= $this->lowRatingThreshold()) {
                $booking->vendorProfile?->user?->notify(new CrmNotification('taxi_review_low_rating', [
                    'taxi_booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'taxi_review_id' => $review->id,
                    'overall_rating' => $review->overall_rating,
                ]));

                foreach (User::where('role', 'admin')->limit(25)->get() as $admin) {
                    $admin->notify(new CrmNotification('taxi_review_low_rating', [
                        'taxi_booking_id' => $booking->id,
                        'reference' => $booking->reference,
                        'taxi_review_id' => $review->id,
                        'overall_rating' => $review->overall_rating,
                    ]));
                }
            }
        } catch (\Throwable) {
            // Reviews must never fail because notifications do.
        }
    }

    protected function notifyApproved(TaxiReview $review): void
    {
        try {
            $review->customer?->notify(new CrmNotification('taxi_review_approved', [
                'taxi_booking_id' => $review->taxi_booking_id,
                'taxi_review_id' => $review->id,
            ]));

            $review->driver?->user?->notify(new CrmNotification('taxi_review_driver_feedback', [
                'taxi_booking_id' => $review->taxi_booking_id,
                'taxi_review_id' => $review->id,
                'overall_rating' => $review->overall_rating,
            ]));
        } catch (\Throwable) {
        }
    }

    protected function notifyRejected(TaxiReview $review): void
    {
        try {
            $review->customer?->notify(new CrmNotification('taxi_review_rejected', [
                'taxi_booking_id' => $review->taxi_booking_id,
                'taxi_review_id' => $review->id,
            ]));
        } catch (\Throwable) {
        }
    }
}
