<?php

namespace App\Services\Discovery;

/**
 * Hotel search query value object (Phase 13C).
 *
 * Normalized parameter contract for the future premium frontend:
 * q, location_type/location_id, check_in/check_out, rooms/adults/
 * children, filters, sort, page/per_page. Sort keys are whitelisted
 * downstream — never passed raw to orderBy.
 */
final class HotelSearchQuery
{
    /**
     * @param  array<int, int>  $amenities
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $locationType = null,
        public readonly ?int $locationId = null,
        public readonly ?string $checkIn = null,
        public readonly ?string $checkOut = null,
        public readonly int $rooms = 1,
        public readonly int $adults = 2,
        public readonly int $children = 0,
        public readonly ?int $propertyTypeId = null,
        public readonly ?int $starRating = null,
        public readonly array $amenities = [],
        public readonly ?string $mealPlan = null,
        public readonly ?string $cancellationMode = null,
        public readonly ?string $minPrice = null,
        public readonly ?string $maxPrice = null,
        public readonly string $priceCurrency = 'USD',
        public readonly ?float $minRating = null,
        public readonly string $sort = 'recommended',
        public readonly int $page = 1,
        public readonly int $perPage = 12,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request data
     */
    public static function fromArray(array $input): self
    {
        $amenities = collect($input['amenities'] ?? [])
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()->take(10)->values()->all();

        return new self(
            q: isset($input['q']) && is_string($input['q']) ? SearchTerm::normalize($input['q']) : null,
            locationType: $input['location_type'] ?? null,
            locationId: isset($input['location_id']) ? (int) $input['location_id'] : null,
            checkIn: $input['check_in'] ?? null,
            checkOut: $input['check_out'] ?? null,
            rooms: max(1, min(10, (int) ($input['rooms'] ?? 1))),
            adults: max(1, min(20, (int) ($input['adults'] ?? 2))),
            children: max(0, min(20, (int) ($input['children'] ?? 0))),
            propertyTypeId: isset($input['property_type_id']) ? (int) $input['property_type_id'] : null,
            starRating: isset($input['star_rating']) ? (int) $input['star_rating'] : null,
            amenities: $amenities,
            mealPlan: $input['meal_plan'] ?? null,
            cancellationMode: $input['cancellation_mode'] ?? null,
            minPrice: isset($input['min_price']) ? (string) $input['min_price'] : null,
            maxPrice: isset($input['max_price']) ? (string) $input['max_price'] : null,
            priceCurrency: strtoupper((string) ($input['price_currency'] ?? 'USD')),
            minRating: isset($input['min_rating']) ? (float) $input['min_rating'] : null,
            sort: (string) ($input['sort'] ?? 'recommended'),
            page: max(1, min(1000, (int) ($input['page'] ?? 1))),
            perPage: max(1, min(24, (int) ($input['per_page'] ?? 12))),
        );
    }

    public function hasDates(): bool
    {
        return $this->checkIn !== null && $this->checkOut !== null;
    }

    public function hasPriceFilter(): bool
    {
        return $this->minPrice !== null || $this->maxPrice !== null;
    }
}
