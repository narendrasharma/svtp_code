<?php

namespace App\Http\Controllers\Taxi;

use App\Http\Controllers\Controller;
use App\Models\TaxiBooking;
use App\Models\TaxiReview;
use App\Models\VendorProfile;
use App\Services\TaxiRatingSummaryService;
use App\Services\TaxiReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taxi reviews across the three authenticated portals (Phase 12A.12).
 *
 * One controller, strict per-portal scoping like TaxiChangeController:
 * customers see only their own bookings/reviews, vendors only their own
 * vendor profile rows, admins everything (staff-permission gates apply).
 * Tracking tokens stay read-only — no anonymous submission endpoint
 * exists here.
 */
class TaxiReviewController extends Controller
{
    public function __construct(
        protected TaxiReviewService $reviews,
        protected TaxiRatingSummaryService $summaries,
    ) {}

    /**
     * @return array{portal: string, vendorId: ?int}
     */
    protected function scope(Request $request, ?TaxiReview $review = null, ?TaxiBooking $booking = null): array
    {
        if ($request->routeIs('account.*')) {
            if ($review) {
                abort_unless($review->customer_user_id !== null && (int) $review->customer_user_id === (int) $request->user()->id, 404);
            }

            if ($booking) {
                abort_unless($booking->customer_user_id !== null && (int) $booking->customer_user_id === (int) $request->user()->id, 404);
            }

            return ['portal' => 'account', 'vendorId' => null];
        }

        if ($request->routeIs('vendor.*')) {
            $profile = $request->user()->vendorProfile;
            abort_unless($profile && $profile->is_active, 403);

            if ($review) {
                abort_unless($review->vendor_profile_id !== null && (int) $review->vendor_profile_id === (int) $profile->id, 404);
            }

            if ($booking) {
                abort_unless((int) $booking->vendor_profile_id === (int) $profile->id, 404);
            }

            return ['portal' => 'vendor', 'vendorId' => (int) $profile->id];
        }

        return ['portal' => 'admin', 'vendorId' => null];
    }

    public function index(Request $request): Response
    {
        ['portal' => $portal, 'vendorId' => $vendorId] = $this->scope($request);

        if ($portal === 'admin') {
            abort_unless($request->user()->can('taxi.reviews.view'), 403);
        }

        $filters = $request->only(['search', 'vendor_id', 'driver_id', 'status', 'rating', 'date_from', 'date_to']);

        $query = TaxiReview::with([
            'booking:id,reference,status,pickup_at',
            'customer:id,name',
            'driver:id,first_name,last_name',
            'vendorProfile:id,business_name',
            'vehicle:id,name,registration_number',
        ])->latest('submitted_at');

        if ($portal === 'account') {
            $query->where('customer_user_id', $request->user()->id);
        } elseif ($vendorId !== null) {
            $query->where('vendor_profile_id', $vendorId);
        }

        if ($portal === 'admin' && ! empty($filters['vendor_id'])) {
            $query->where('vendor_profile_id', (int) $filters['vendor_id']);
        }

        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', (int) $filters['driver_id']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], TaxiReview::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['rating']) && in_array((int) $filters['rating'], [1, 2, 3, 4, 5], true)) {
            $query->where('overall_rating', (int) $filters['rating']);
        }

        if (! empty($filters['search'])) {
            $search = mb_substr(trim((string) $filters['search']), 0, 60);
            $query->whereHas('booking', fn ($q) => $q->where('reference', 'like', '%'.$search.'%'));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('submitted_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('submitted_at', '<=', $filters['date_to']);
        }

        $reviews = $query->paginate(15)->withQueryString();
        $reviews->getCollection()->transform(fn (TaxiReview $review): array => $this->rowFor($review, $portal));

        return Inertia::render('Taxi/Reviews/Index', [
            'portal' => $portal,
            'reviews' => $reviews,
            'filters' => $filters,
            'statuses' => TaxiReview::STATUSES,
            'vendors' => $portal === 'admin' ? VendorProfile::orderBy('business_name')->get(['id', 'business_name']) : [],
            'summary' => $portal === 'vendor' && $vendorId ? $this->summaries->forVendor(VendorProfile::findOrFail($vendorId)) : ($portal === 'admin' ? $this->summaries->platform() : null),
            'canModerate' => $portal === 'admin' && $request->user()->can('taxi.reviews.moderate'),
            'lowRatingThreshold' => $this->reviews->lowRatingThreshold(),
        ]);
    }

    public function show(Request $request, TaxiReview $review): Response
    {
        ['portal' => $portal] = $this->scope($request, $review);
        $review->load(['booking', 'customer:id,name', 'driver:id,first_name,last_name', 'vendorProfile:id,business_name', 'vehicle:id,name,registration_number']);

        return Inertia::render('Taxi/Reviews/Show', [
            'portal' => $portal,
            'review' => $this->detailFor($review, $portal),
            'canModerate' => $portal === 'admin' && $request->user()->can('taxi.reviews.moderate'),
            'canReply' => $portal !== 'account' && $this->reviews->allowVendorReply()
                && ($portal === 'vendor' || $request->user()->can('taxi.reviews.reply')),
            'flagReasons' => TaxiReview::FLAG_REASONS,
        ]);
    }

    /**
     * Customer review form for an OWNED completed booking.
     */
    public function create(Request $request, TaxiBooking $booking): Response
    {
        $this->scope($request, null, $booking);
        $booking->load(['assignedDriver:id,first_name,last_name', 'vehicleType:id,name']);

        $existing = TaxiReview::where('taxi_booking_id', $booking->id)
            ->where('customer_user_id', $request->user()->id)
            ->first();

        return Inertia::render('Taxi/Reviews/Form', [
            'booking' => $booking->only(['id', 'reference', 'status', 'pickup_at', 'trip_type']),
            'eligibility' => $this->reviews->eligibility($booking, $request->user()),
            'existing' => $existing ? $this->detailFor($existing->load(['driver', 'vehicle']), 'account') : null,
            'allowText' => $this->reviews->allowTextReview(),
        ]);
    }

    /**
     * Authenticated customer submission for an OWNED booking.
     */
    public function store(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $this->scope($request, null, $booking);

        $data = $request->validate([
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'driver_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'vehicle_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'service_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'punctuality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'cleanliness_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reviews->submit($booking, $request->user(), $data);

        return back()->with('flash', $this->reviews->requireModeration()
            ? 'Thank you — your review was submitted and is awaiting moderation.'
            : 'Thank you — your review has been published.');
    }

    public function moderate(Request $request, TaxiReview $review): RedirectResponse
    {
        ['portal' => $portal] = $this->scope($request, $review);
        abort_unless($portal === 'admin' && $request->user()->can('taxi.reviews.moderate'), 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'hide', 'unhide'])],
            'moderation_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->reviews->moderate($review, $data['action'], $request->user(), $data['moderation_note'] ?? null);

        return back()->with('flash', 'Review moderation recorded.');
    }

    public function reply(Request $request, TaxiReview $review): RedirectResponse
    {
        ['portal' => $portal] = $this->scope($request, $review);
        abort_unless($portal !== 'account' && ($portal === 'vendor' || $request->user()->can('taxi.reviews.reply')), 403);

        $data = $request->validate(['reply' => ['required', 'string', 'max:1000']]);

        $this->reviews->reply($review, $request->user(), $data['reply']);

        return back()->with('flash', 'Vendor reply recorded.');
    }

    public function flag(Request $request, TaxiReview $review): RedirectResponse
    {
        ['portal' => $portal] = $this->scope($request, $review);
        abort_unless($portal !== 'account', 403);

        $data = $request->validate([
            'reason' => ['required', Rule::in(TaxiReview::FLAG_REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->reviews->flag($review, $request->user(), $data['reason'], $data['note'] ?? null);

        return back()->with('flash', 'Review flagged for admin moderation. Visibility is unchanged until an admin decides.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowFor(TaxiReview $review, string $portal): array
    {
        return [
            'id' => $review->id,
            'booking_reference' => $review->booking?->reference,
            'customer' => TaxiReviewService::displayName($review->customer),
            'vendor' => $review->vendorProfile?->business_name,
            'driver' => $review->driver ? trim($review->driver->first_name.' '.($review->driver->last_name ?? '')) : null,
            'overall_rating' => $review->overall_rating,
            'status' => $review->status,
            'submitted_at' => $review->submitted_at?->toDateTimeString(),
            'flagged' => $portal === 'admin' ? $review->flagged_at !== null : (bool) ($review->flagged_at !== null && $portal === 'vendor'),
            'low' => $review->overall_rating <= $this->reviews->lowRatingThreshold(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailFor(TaxiReview $review, string $portal): array
    {
        $base = [
            'id' => $review->id,
            'overall_rating' => $review->overall_rating,
            'driver_rating' => $review->driver_rating,
            'vehicle_rating' => $review->vehicle_rating,
            'service_rating' => $review->service_rating,
            'punctuality_rating' => $review->punctuality_rating,
            'cleanliness_rating' => $review->cleanliness_rating,
            'comment' => $review->comment,
            'status' => $review->status,
            'submitted_at' => $review->submitted_at?->toDateTimeString(),
            'approved_at' => $review->approved_at?->toDateTimeString(),
            'customer' => TaxiReviewService::displayName($review->customer),
            'driver' => $review->driver ? trim($review->driver->first_name.' '.($review->driver->last_name ?? '')) : null,
            'vehicle' => $review->vehicle ? trim(($review->vehicle->name ?? '').' '.($review->vehicle->registration_number ?? '')) : null,
            'vendor_reply' => $review->vendor_reply,
            'vendor_replied_at' => $review->vendor_replied_at?->toDateTimeString(),
        ];

        if ($portal === 'account') {
            return $base;
        }

        $base['booking_reference'] = $review->booking?->reference;
        $base['booking_status'] = $review->booking?->status;
        $base['vendor'] = $review->vendorProfile?->business_name;
        $base['flag_reason'] = $review->flag_reason;
        $base['flagged_at'] = $review->flagged_at?->toDateTimeString();

        if ($portal === 'admin') {
            $base['moderation_note'] = $review->moderation_note;
            $base['rejected_at'] = $review->rejected_at?->toDateTimeString();
        }

        return $base;
    }
}
