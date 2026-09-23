<?php

namespace App\Services\Discovery;

/**
 * Tour search query value object (Phase 13C).
 *
 * Only concepts the current Tour booking model supports: location
 * (city/destination/place), travel date, party size, category, duration,
 * price range, tags, featured, rating. No invented concepts.
 */
final class TourSearchQuery
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $locationType = null,
        public readonly ?int $locationId = null,
        public readonly ?string $travelDate = null,
        public readonly int $adults = 1,
        public readonly int $children = 0,
        public readonly ?int $categoryId = null,
        public readonly ?int $minDuration = null,
        public readonly ?int $maxDuration = null,
        public readonly ?string $minPrice = null,
        public readonly ?string $maxPrice = null,
        public readonly array $tags = [],
        public readonly bool $featuredOnly = false,
        public readonly ?float $minRating = null,
        public readonly string $sort = 'recommended',
        public readonly int $page = 1,
        public readonly int $perPage = 9,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request data
     */
    public static function fromArray(array $input): self
    {
        $tags = collect($input['tags'] ?? [])
            ->filter(fn ($tag): bool => is_string($tag) && $tag !== '')
            ->unique()->take(20)->values()->all();

        return new self(
            q: isset($input['q']) && is_string($input['q']) ? SearchTerm::normalize($input['q']) : null,
            locationType: $input['location_type'] ?? null,
            locationId: isset($input['location_id']) ? (int) $input['location_id'] : null,
            travelDate: $input['travel_date'] ?? null,
            adults: max(1, min(60, (int) ($input['adults'] ?? 1))),
            children: max(0, min(60, (int) ($input['children'] ?? 0))),
            categoryId: isset($input['category_id']) ? (int) $input['category_id'] : null,
            minDuration: isset($input['min_duration']) ? (int) $input['min_duration'] : null,
            maxDuration: isset($input['max_duration']) ? (int) $input['max_duration'] : null,
            minPrice: isset($input['min_price']) ? (string) $input['min_price'] : null,
            maxPrice: isset($input['max_price']) ? (string) $input['max_price'] : null,
            tags: $tags,
            featuredOnly: (bool) ($input['featured'] ?? false),
            minRating: isset($input['min_rating']) ? (float) $input['min_rating'] : null,
            sort: (string) ($input['sort'] ?? 'recommended'),
            page: max(1, min(1000, (int) ($input['page'] ?? 1))),
            perPage: max(1, min(24, (int) ($input['per_page'] ?? 9))),
        );
    }
}
