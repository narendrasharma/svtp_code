<?php

namespace App\Services\Discovery;

use App\Models\Destination;
use App\Models\HotelRatePlan;
use App\Models\Place;
use App\Models\Property;
use App\Services\HotelAvailabilityService;
use App\Services\MoneyPresenter;
use App\Support\HotelSettings;
use App\Support\Localization;
use App\Support\ModuleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hotel search contract (Phase 13C).
 *
 * Published properties only, Hotels module gated. Availability reuses
 * HotelAvailabilityService (bounded: at most 50 candidates checked per
 * search, one bulk inventory query per property via the service).
 * Card prices are "starting from" presentation only — the booking quote
 * remains authoritative. Display money never rewrites stored rates.
 *
 * Price filter semantics (constrained, documented): min/max are compared
 * in `price_currency` (default hotel default currency) against active
 * plan base rates in that same currency. Plans in other currencies do
 * not match a price-filtered search — this avoids wrong cross-currency
 * math until a grouped FX filter lands.
 *
 * Recommended sort is deterministic: featured first → rating_average
 * desc → id asc. No marketing bias, no ML claims.
 */
final class HotelSearchService
{
    /**
     * @return array<int, string>
     */
    public static function allowedSorts(): array
    {
        return config('search.hotel_sorts', ['recommended', 'price_asc', 'price_desc', 'rating_desc']);
    }

    public function __construct(
        protected HotelAvailabilityService $availability,
        protected ModuleManager $modules,
    ) {}

    public function available(): bool
    {
        return $this->modules->isEnabled(ModuleManager::HOTELS);
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,meta:array{current_page:int,per_page:int,total:int,last_page:int},facets:array<string,mixed>,sort:string,seo:array{noindex:bool,canonical:?string}}
     */
    public function search(HotelSearchQuery $query, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();

        if (! $this->available()) {
            return $this->empty($query);
        }

        $base = $this->baseQuery($query);

        // Availability pre-filter (bounded adapter): resolve up to 50
        // recommended candidates, keep those with ≥1 available room type.
        // Next optimization seam: push availability into SQL for large
        // catalogs instead of per-property service checks.
        if ($query->hasDates()) {
            $candidateIds = (clone $base)->orderByDesc('properties.is_featured')
                ->orderByDesc('properties.rating_average')
                ->orderBy('properties.id')
                ->limit(50)->pluck('properties.id')->all();

            $availableIds = $this->availableIds($candidateIds, $query);

            $base->whereIn('properties.id', $availableIds === [] ? [-1] : $availableIds);
        }

        $facets = $this->facets(clone $base, $query);

        $total = (clone $base)->count();

        $this->applySort($base, $query);

        $perPage = $query->perPage;
        $page = $query->page;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $properties = $base
            ->with(['propertyType:id,name,slug', 'city:id,name', 'destination:id,name', 'images'])
            ->offset(($page - 1) * $perPage)->limit($perPage)
            ->get();

        $startingPrices = $this->startingPrices($properties->pluck('id')->all());

        $data = $properties->map(fn (Property $property): array => $this->card(
            $property,
            $startingPrices[$property->id] ?? null,
            $locale
        ))->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
            'facets' => $facets,
            'sort' => in_array($query->sort, self::allowedSorts(), true) ? $query->sort : 'recommended',
            'seo' => [
                'noindex' => true,
                'canonical' => route('search.hotels', [], false),
            ],
        ];
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,meta:array{current_page:int,per_page:int,total:int,last_page:int},facets:array<string,mixed>,sort:string,seo:array{noindex:bool,canonical:?string}}
     */
    private function empty(HotelSearchQuery $query): array
    {
        return [
            'data' => [],
            'meta' => ['current_page' => 1, 'per_page' => $query->perPage, 'total' => 0, 'last_page' => 1],
            'facets' => ['property_types' => [], 'rating_bands' => [], 'amenities' => [], 'meal_plans' => []],
            'sort' => 'recommended',
            'seo' => ['noindex' => true, 'canonical' => route('search.hotels', [], false)],
        ];
    }

    private function baseQuery(HotelSearchQuery $query): Builder
    {
        $base = Property::query()->from('properties')->published();

        if (is_string($query->q) && $query->q !== '') {
            $like = SearchTerm::like($query->q);
            $base->where(fn ($inner) => $inner->where('properties.name', 'like', $like)
                ->orWhere('properties.address_line_1', 'like', $like));
        }

        $this->applyLocationFilter($base, $query);

        if ($query->propertyTypeId !== null) {
            $base->where('properties.property_type_id', $query->propertyTypeId);
        }

        if ($query->starRating !== null) {
            $base->where('properties.star_rating', $query->starRating);
        }

        // Approved-only aggregate columns (maintained by
        // HotelRatingSummaryService::refresh on approved reviews only).
        if ($query->minRating !== null) {
            $base->where('properties.rating_average', '>=', number_format(max(0, min(5, $query->minRating)), 2, '.', ''));
        }

        foreach ($query->amenities as $amenityId) {
            $base->whereHas('amenities', fn ($inner) => $inner->where('hotel_amenities.id', $amenityId));
        }

        if ($query->mealPlan !== null) {
            $base->whereExists(function ($exists) use ($query): void {
                $exists->select(DB::raw(1))->from('hotel_rate_plans')
                    ->whereColumn('hotel_rate_plans.property_id', 'properties.id')
                    ->where('hotel_rate_plans.is_active', true)
                    ->where('hotel_rate_plans.meal_plan', $query->mealPlan);
            });
        }

        if ($query->cancellationMode !== null) {
            $base->whereExists(function ($exists) use ($query): void {
                $exists->select(DB::raw(1))->from('hotel_rate_plans')
                    ->whereColumn('hotel_rate_plans.property_id', 'properties.id')
                    ->where('hotel_rate_plans.is_active', true)
                    ->where('hotel_rate_plans.cancellation_mode', $query->cancellationMode);
            });
        }

        if ($query->hasPriceFilter()) {
            $currency = preg_match('/^[A-Z]{3}$/', $query->priceCurrency) === 1
                ? $query->priceCurrency
                : HotelSettings::defaultCurrency();

            $base->whereExists(function ($exists) use ($query, $currency): void {
                $exists->select(DB::raw(1))->from('hotel_rate_plans')
                    ->whereColumn('hotel_rate_plans.property_id', 'properties.id')
                    ->where('hotel_rate_plans.is_active', true)
                    ->where('hotel_rate_plans.currency', $currency);

                if ($query->minPrice !== null && is_numeric($query->minPrice)) {
                    $exists->where('hotel_rate_plans.base_rate', '>=', number_format((float) $query->minPrice, 2, '.', ''));
                }

                if ($query->maxPrice !== null && is_numeric($query->maxPrice)) {
                    $exists->where('hotel_rate_plans.base_rate', '<=', number_format((float) $query->maxPrice, 2, '.', ''));
                }
            });
        }

        return $base;
    }

    /**
     * Location scoping kept beside the query builder for clarity.
     */
    private function applyLocationFilter(Builder $base, HotelSearchQuery $query): void
    {
        if ($query->locationType === null || $query->locationId === null) {
            return;
        }

        match ($query->locationType) {
            'city' => $base->where('properties.city_id', $query->locationId),
            'destination' => $this->applyDestination($base, $query->locationId),
            'place' => $this->applyPlace($base, $query->locationId),
            default => null,
        };
    }

    private function applyDestination(Builder $base, int $destinationId): void
    {
        $destination = Destination::query()->find($destinationId);

        if (! $destination) {
            $base->whereRaw('1 = 0');

            return;
        }

        // Destination context: direct links plus city-level coverage.
        $base->where(function ($inner) use ($destination): void {
            $inner->where('properties.destination_id', $destination->id);

            if ($destination->city_id !== null) {
                $inner->orWhere('properties.city_id', $destination->city_id);
            }
        });
    }

    private function applyPlace(Builder $base, int $placeId): void
    {
        $place = Place::query()->with('destination:id,city_id')->find($placeId);

        if (! $place || ! $place->destination) {
            $base->whereRaw('1 = 0');

            return;
        }

        $this->applyDestination($base, (int) $place->destination->id);
    }

    /**
     * @param  array<int, int>  $candidateIds
     * @return array<int, int>
     */
    private function availableIds(array $candidateIds, HotelSearchQuery $query): array
    {
        if ($candidateIds === [] || ! $query->hasDates()) {
            return $candidateIds;
        }

        /** @var Collection<int, Property> $properties */
        $properties = Property::query()->whereKey($candidateIds)->published()->get()->keyBy('id');

        $out = [];

        foreach ($candidateIds as $id) {
            $property = $properties->get($id);

            if (! $property) {
                continue;
            }

            try {
                $checks = $this->availability->availableRoomTypes(
                    $property,
                    (string) $query->checkIn,
                    (string) $query->checkOut,
                    $query->rooms
                );
            } catch (\Throwable) {
                continue;
            }

            if (collect($checks)->contains('available', true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    private function applySort(Builder $base, HotelSearchQuery $query): void
    {
        $sort = in_array($query->sort, self::allowedSorts(), true) ? $query->sort : 'recommended';

        match ($sort) {
            'price_asc' => $base->select('properties.*')
                ->selectSub(
                    'select min(base_rate) from hotel_rate_plans where hotel_rate_plans.property_id = properties.id and hotel_rate_plans.is_active = 1',
                    'starting_rate'
                )->orderByRaw('starting_rate is null, starting_rate asc')->orderBy('properties.id'),
            'price_desc' => $base->select('properties.*')
                ->selectSub(
                    'select min(base_rate) from hotel_rate_plans where hotel_rate_plans.property_id = properties.id and hotel_rate_plans.is_active = 1',
                    'starting_rate'
                )->orderByRaw('starting_rate is null, starting_rate desc')->orderBy('properties.id'),
            'rating_desc' => $base->orderByDesc('properties.rating_average')
                ->orderByDesc('properties.is_featured')->orderBy('properties.id'),
            default => $base->orderByDesc('properties.is_featured')
                ->orderByDesc('properties.rating_average')->orderBy('properties.id'),
        };
    }

    /**
     * @param  array<int, int>  $propertyIds
     * @return array<int, array{amount:string,currency:string}>
     */
    private function startingPrices(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $rows = HotelRatePlan::query()
            ->whereIn('property_id', $propertyIds)
            ->where('is_active', true)
            ->selectRaw('property_id, currency, MIN(base_rate) as min_rate')
            ->groupBy('property_id', 'currency')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $id = (int) $row->property_id;

            if (! isset($out[$id])) {
                $out[$id] = ['amount' => number_format((float) $row->min_rate, 2, '.', ''), 'currency' => (string) $row->currency];
            }
        }

        return $out;
    }

    /**
     * Compact public-safe hotel card. No gallery, no vendor contact,
     * no internal notes — cover image only plus approved aggregates.
     *
     * @param  array{amount:string,currency:string}|null  $starting
     * @return array<string, mixed>
     */
    private function card(Property $property, ?array $starting, string $locale): array
    {
        $primary = $property->images->firstWhere('is_primary', true) ?? $property->images->first();

        $price = null;
        $displayMoney = null;

        if ($starting !== null) {
            $price = ['amount' => $starting['amount'], 'currency' => $starting['currency']];
            $displayMoney = MoneyPresenter::present($starting['amount'], $starting['currency']);
        }

        return [
            'id' => (int) $property->id,
            'slug' => (string) $property->slug,
            'name' => DiscoveryResult::displayName($property, 'name', $locale) ?? $property->name,
            'image' => $primary?->url(),
            'city' => $property->city?->name,
            'destination' => $property->destination?->name,
            'property_type' => $property->propertyType?->name,
            'star_rating' => $property->star_rating !== null ? (int) $property->star_rating : null,
            'rating_average' => $property->rating_average !== null ? (string) $property->rating_average : null,
            'reviews_count' => (int) ($property->reviews_count ?? 0),
            'starting_price' => $price,
            'display_money' => $displayMoney,
            'badges' => array_values(array_filter([
                $property->is_featured ? 'featured' : null,
            ])),
            'url' => route('hotels.show', $property->slug, false),
        ];
    }

    /**
     * Facet counts use public entities only, via grouped aggregates
     * (one query per facet, never per option).
     *
     * @return array{property_types:array<int,array{id:int,name:string,count:int}>,rating_bands:array<int,array{min:float,count:int}>,amenities:array<int,array{id:int,name:string,count:int}>,meal_plans:array<int,array{value:string,count:int}>}
     */
    private function facets(Builder $base, HotelSearchQuery $query): array
    {
        $ids = (clone $base)->pluck('properties.id')->all();

        if ($ids === []) {
            return ['property_types' => [], 'rating_bands' => [], 'amenities' => [], 'meal_plans' => []];
        }

        $types = Property::query()->whereIn('properties.id', $ids)
            ->join('property_types', 'property_types.id', '=', 'properties.property_type_id')
            ->selectRaw('property_types.id as id, property_types.name as name, COUNT(*) as count')
            ->groupBy('property_types.id', 'property_types.name')
            ->orderBy('name')->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'count' => (int) $row->count])
            ->all();

        $bands = [];

        foreach ([4.5, 4.0, 3.0] as $min) {
            $bands[] = [
                'min' => $min,
                'count' => Property::query()->whereIn('id', $ids)
                    ->where('rating_average', '>=', number_format($min, 2, '.', ''))->count(),
            ];
        }

        $amenities = DB::table('hotel_amenity_property')
            ->join('hotel_amenities', 'hotel_amenities.id', '=', 'hotel_amenity_property.hotel_amenity_id')
            ->whereIn('hotel_amenity_property.property_id', $ids)
            ->where('hotel_amenities.is_active', true)
            ->selectRaw('hotel_amenities.id as id, hotel_amenities.name as name, COUNT(DISTINCT hotel_amenity_property.property_id) as count')
            ->groupBy('hotel_amenities.id', 'hotel_amenities.name')
            ->orderBy('hotel_amenities.name')->limit(20)->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'count' => (int) $row->count])
            ->all();

        $meals = HotelRatePlan::query()->whereIn('property_id', $ids)
            ->where('is_active', true)
            ->selectRaw('meal_plan as value, COUNT(DISTINCT property_id) as count')
            ->groupBy('meal_plan')->orderBy('meal_plan')->get()
            ->map(fn ($row): array => ['value' => (string) $row->value, 'count' => (int) $row->count])
            ->all();

        return [
            'property_types' => $types,
            'rating_bands' => $bands,
            'amenities' => $amenities,
            'meal_plans' => $meals,
        ];
    }
}
