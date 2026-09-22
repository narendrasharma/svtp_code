<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\TaxiReview;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Support\TaxiSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reusable taxi rating aggregates (Phase 12A.12).
 *
 * Query-based only — no stored averages, so moderation changes can
 * never leave stale numbers behind. Only approved reviews contribute.
 * Averages round to one decimal. Public display additionally honours
 * the minimum-review-count gate.
 */
class TaxiRatingSummaryService
{
    /**
     * @return array{count: int, overall_avg: ?float, driver_avg: ?float, vehicle_avg: ?float, service_avg: ?float, punctuality_avg: ?float, cleanliness_avg: ?float, distribution: array<int, int>, low_rated_count: int}
     */
    public function forDriver(Driver $driver): array
    {
        return $this->summarize(TaxiReview::where('driver_id', $driver->id));
    }

    /**
     * @return array{count: int, overall_avg: ?float, driver_avg: ?float, vehicle_avg: ?float, service_avg: ?float, punctuality_avg: ?float, cleanliness_avg: ?float, distribution: array<int, int>, low_rated_count: int}
     */
    public function forVendor(VendorProfile $profile): array
    {
        return $this->summarize(TaxiReview::where('vendor_profile_id', $profile->id));
    }

    /**
     * @return array{count: int, overall_avg: ?float, driver_avg: ?float, vehicle_avg: ?float, service_avg: ?float, punctuality_avg: ?float, cleanliness_avg: ?float, distribution: array<int, int>, low_rated_count: int}
     */
    public function forVehicle(Vehicle $vehicle): array
    {
        return $this->summarize(TaxiReview::where('vehicle_id', $vehicle->id));
    }

    /**
     * @return array{count: int, overall_avg: ?float, driver_avg: ?float, vehicle_avg: ?float, service_avg: ?float, punctuality_avg: ?float, cleanliness_avg: ?float, distribution: array<int, int>, low_rated_count: int}
     */
    public function forBooking(TaxiBooking $booking): array
    {
        return $this->summarize(TaxiReview::where('taxi_booking_id', $booking->id));
    }

    /**
     * Platform-wide quality metrics for the Admin desk.
     *
     * @return array{count: int, overall_avg: ?float, low_rated_count: int, distribution: array<int, int>}
     */
    public function platform(): array
    {
        $summary = $this->summarize(TaxiReview::query());

        return [
            'count' => $summary['count'],
            'overall_avg' => $summary['overall_avg'],
            'low_rated_count' => $summary['low_rated_count'],
            'distribution' => $summary['distribution'],
        ];
    }

    /**
     * Public-safe aggregate: approved reviews only, gated by the
     * minimum-review-count setting.
     *
     * @return array{count: int, overall_avg: ?float, visible: bool}
     */
    public function publicForDriver(Driver $driver): array
    {
        if (! TaxiSettings::enabled('taxi.reviews.show_driver_rating_publicly')) {
            return ['count' => 0, 'overall_avg' => null, 'visible' => false];
        }

        return $this->gated(TaxiReview::where('driver_id', $driver->id));
    }

    /**
     * @return array{count: int, overall_avg: ?float, visible: bool}
     */
    public function publicForVendor(VendorProfile $profile): array
    {
        if (! TaxiSettings::enabled('taxi.reviews.show_vendor_rating_publicly')) {
            return ['count' => 0, 'overall_avg' => null, 'visible' => false];
        }

        return $this->gated(TaxiReview::where('vendor_profile_id', $profile->id));
    }

    /**
     * @param  Builder<TaxiReview>  $query
     * @return array{count: int, overall_avg: ?float, visible: bool}
     */
    protected function gated($query): array
    {
        $minimum = max(1, (int) (TaxiSettings::get('taxi.reviews.minimum_reviews_for_public_average') ?? 5));
        $approved = (clone $query)->where('status', TaxiReview::STATUS_APPROVED);
        $count = (clone $approved)->count();

        if ($count < $minimum) {
            return ['count' => $count, 'overall_avg' => null, 'visible' => false];
        }

        return ['count' => $count, 'overall_avg' => $this->oneDecimal((clone $approved)->avg('overall_rating')), 'visible' => true];
    }

    /**
     * @param  Builder<TaxiReview>  $query
     * @return array{count: int, overall_avg: ?float, driver_avg: ?float, vehicle_avg: ?float, service_avg: ?float, punctuality_avg: ?float, cleanliness_avg: ?float, distribution: array<int, int>, low_rated_count: int}
     */
    protected function summarize($query): array
    {
        $approved = (clone $query)->where('status', TaxiReview::STATUS_APPROVED);
        $threshold = app(TaxiReviewService::class)->lowRatingThreshold();

        $distribution = (clone $approved)
            ->select('overall_rating', DB::raw('count(*) as total'))
            ->groupBy('overall_rating')
            ->pluck('total', 'overall_rating')
            ->all();

        $full = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($distribution as $rating => $total) {
            $full[(int) $rating] = (int) $total;
        }

        return [
            'count' => (clone $approved)->count(),
            'overall_avg' => $this->oneDecimal((clone $approved)->avg('overall_rating')),
            'driver_avg' => $this->oneDecimal((clone $approved)->whereNotNull('driver_rating')->avg('driver_rating')),
            'vehicle_avg' => $this->oneDecimal((clone $approved)->whereNotNull('vehicle_rating')->avg('vehicle_rating')),
            'service_avg' => $this->oneDecimal((clone $approved)->whereNotNull('service_rating')->avg('service_rating')),
            'punctuality_avg' => $this->oneDecimal((clone $approved)->whereNotNull('punctuality_rating')->avg('punctuality_rating')),
            'cleanliness_avg' => $this->oneDecimal((clone $approved)->whereNotNull('cleanliness_rating')->avg('cleanliness_rating')),
            'distribution' => $full,
            'low_rated_count' => (clone $approved)->where('overall_rating', '<=', $threshold)->count(),
        ];
    }

    protected function oneDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return round((float) $value, 1);
    }
}
