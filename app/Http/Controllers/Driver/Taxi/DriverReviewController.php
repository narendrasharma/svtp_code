<?php

namespace App\Http\Controllers\Driver\Taxi;

use App\Models\TaxiReview;
use App\Services\TaxiRatingSummaryService;
use App\Services\TaxiReviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver feedback desk (Phase 12A.12, read-only).
 *
 * Drivers see only their OWN approved ratings and feedback — never
 * moderation internals, flags, other drivers' rows, or customer
 * private data.
 */
class DriverReviewController extends DriverPortalController
{
    public function __construct(
        protected TaxiRatingSummaryService $summaries,
    ) {}

    public function index(Request $request): Response
    {
        $driver = $this->driver($request);

        $feedback = TaxiReview::with(['booking:id,reference,pickup_at,trip_type'])
            ->where('driver_id', $driver->id)
            ->where('status', TaxiReview::STATUS_APPROVED)
            ->latest('submitted_at')
            ->paginate(15)
            ->withQueryString();

        $feedback->getCollection()->transform(fn (TaxiReview $review): array => [
            'id' => $review->id,
            'booking_reference' => $review->booking?->reference,
            'trip_type' => $review->booking?->trip_type,
            'pickup_at' => $review->booking?->pickup_at?->toDateTimeString(),
            'customer' => TaxiReviewService::displayName($review->customer),
            'overall_rating' => $review->overall_rating,
            'driver_rating' => $review->driver_rating,
            'vehicle_rating' => $review->vehicle_rating,
            'service_rating' => $review->service_rating,
            'punctuality_rating' => $review->punctuality_rating,
            'cleanliness_rating' => $review->cleanliness_rating,
            'comment' => $review->comment,
            'vendor_reply' => $review->vendor_reply,
            'submitted_at' => $review->submitted_at?->toDateTimeString(),
        ]);

        return Inertia::render('Driver/Taxi/Reviews/Index', [
            'summary' => $this->summaries->forDriver($driver),
            'feedback' => $feedback,
        ]);
    }
}
