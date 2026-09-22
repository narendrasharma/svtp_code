<?php

namespace App\Services;

use App\Models\HotelReview;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

class HotelRatingSummaryService
{
    /**
     * One bounded aggregate query for a property detail, independent of the
     * review page. Integer sums are rounded half-up to hundredths without floats.
     *
     * @return array{reviews_count: int, rating_average: ?string, distribution: array<int, int>, category_averages: array<string, ?string>}
     */
    public function forProperty(int $propertyId): array
    {
        $query = HotelReview::approved()->where('property_id', $propertyId)->toBase()
            ->selectRaw('COUNT(*) AS review_count, COALESCE(SUM(overall_rating), 0) AS rating_sum');

        foreach (array_keys(HotelReview::CATEGORY_RATINGS) as $column) {
            $query->selectRaw("COALESCE(SUM({$column}), 0) AS {$column}_sum");
        }

        foreach (range(1, 5) as $rating) {
            $query->selectRaw("COALESCE(SUM(CASE WHEN overall_rating = {$rating} THEN 1 ELSE 0 END), 0) AS stars_{$rating}");
        }

        $totals = $query->first();
        $count = (int) $totals->review_count;
        $distribution = [];
        $categories = [];

        foreach (range(5, 1) as $rating) {
            $distribution[$rating] = (int) $totals->{"stars_{$rating}"};
        }

        foreach (array_keys(HotelReview::CATEGORY_RATINGS) as $column) {
            $categories[$column] = $this->average((int) $totals->{"{$column}_sum"}, $count);
        }

        return [
            'reviews_count' => $count,
            'rating_average' => $this->average((int) $totals->rating_sum, $count),
            'distribution' => $distribution,
            'category_averages' => $categories,
        ];
    }

    /**
     * Rebuild compact listing fields inside the review write transaction.
     * Every application review writer locks the property before changing a
     * review. The locking aggregate uses a current read on MySQL, including
     * when a transaction already has a repeatable-read snapshot.
     *
     * No model events/audit noise for derived fields. Reviews remain the source
     * of truth; this method also supports deterministic factory creation.
     */
    public function refresh(int $propertyId): void
    {
        DB::transaction(function () use ($propertyId): void {
            Property::withTrashed()->whereKey($propertyId)->lockForUpdate()->firstOrFail();
            $totals = HotelReview::approved()->where('property_id', $propertyId)->toBase()
                ->selectRaw('COUNT(*) AS review_count, COALESCE(SUM(overall_rating), 0) AS rating_sum')
                ->lockForUpdate()->first();

            Property::withTrashed()->whereKey($propertyId)->toBase()->update([
                'reviews_count' => (int) $totals->review_count,
                'rating_average' => $this->average((int) $totals->rating_sum, (int) $totals->review_count),
            ]);
        }, 3);
    }

    private function average(int $sum, int $count): ?string
    {
        if ($count === 0) {
            return null;
        }

        $hundredths = intdiv($sum * 200 + $count, $count * 2);

        return intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }
}
