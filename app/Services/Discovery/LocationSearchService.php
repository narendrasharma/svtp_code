<?php

namespace App\Services\Discovery;

use App\Contracts\Discovery\SearchProvider;
use App\Enums\PropertyStatus;
use App\Models\City;
use App\Models\Destination;
use App\Models\Property;
use App\Models\TourPackage;
use App\Support\Localization;
use App\Support\ModuleManager;
use Illuminate\Support\Facades\Cache;

/**
 * Shared location discovery orchestration (Phase 13C).
 *
 * Thin boundary over the SearchProvider seam: normalization, bounds,
 * featured/popular collections and grouped global search live here.
 * Domain logic (availability, pricing, facets) lives in the dedicated
 * Hotel/Tour/Taxi services — never in this class.
 */
final class LocationSearchService
{
    public function __construct(
        protected SearchProvider $provider,
        protected ModuleManager $modules,
    ) {}

    /**
     * Unified autocomplete: normalized, bounded, ranked, locale-aware.
     *
     * Empty queries return [] unless $allowEmptyWithFeatured is true, in
     * which case featured cities/destinations fill the panel instead of
     * issuing an expensive broad query per keystroke.
     *
     * @return array<int, array{type:string,id:int,name:string,subtitle:?string,slug:string,image:?string,url:string,coordinates:?array{lat:?float,lng:?float},label:string}>
     */
    public function autocomplete(mixed $raw, ?string $locale = null, ?int $limit = null, bool $allowEmptyWithFeatured = false): array
    {
        $locale ??= Localization::currentLocale();
        $limit ??= (int) config('search.autocomplete_limit', 10);
        $limit = max(1, min(12, $limit));

        $term = SearchTerm::normalize($raw);

        if ($term === '') {
            if (! $allowEmptyWithFeatured) {
                return [];
            }

            return $this->featuredSuggestions($limit, $locale);
        }

        if (! SearchTerm::meetsMinimum($term)) {
            return [];
        }

        return $this->provider->suggest($term, $limit, $locale)->all();
    }

    /**
     * Grouped global discovery for the future header search. Never
     * returns Taxi bookings or operational entities.
     *
     * @return array{cities:array<int,mixed>,destinations:array<int,mixed>,places:array<int,mixed>,properties:array<int,mixed>,tours:array<int,mixed>}
     */
    public function global(mixed $raw, ?string $locale = null, ?int $limit = null): array
    {
        $locale ??= Localization::currentLocale();
        $limit ??= (int) config('search.autocomplete_limit', 10);
        $perType = max(1, min(5, (int) config('search.per_type_limit', 4)));

        $term = SearchTerm::normalize($raw);

        $empty = ['cities' => [], 'destinations' => [], 'places' => [], 'properties' => [], 'tours' => []];

        if ($term === '' || ! SearchTerm::meetsMinimum($term)) {
            return $empty;
        }

        $suggestions = $this->provider->suggest($term, $limit, $locale);

        $grouped = $empty;

        foreach ($suggestions as $row) {
            match ($row['type']) {
                DiscoveryResult::TYPE_CITY => $grouped['cities'][] = $row,
                DiscoveryResult::TYPE_DESTINATION => $grouped['destinations'][] = $row,
                DiscoveryResult::TYPE_PLACE => $grouped['places'][] = $row,
                DiscoveryResult::TYPE_PROPERTY => $grouped['properties'][] = $row,
                DiscoveryResult::TYPE_TOUR => $grouped['tours'][] = $row,
                default => null,
            };
        }

        foreach ($grouped as $key => $items) {
            $grouped[$key] = array_values(array_slice($items, 0, $perType));
        }

        // Hotels/Tours disabled: slices stay empty while geography works.
        if ($this->modules->isDisabled(ModuleManager::HOTELS)) {
            $grouped['properties'] = [];
        }

        if ($this->modules->isDisabled(ModuleManager::TOURS)) {
            $grouped['tours'] = [];
        }

        return $grouped;
    }

    /**
     * @return array<int, array{type:string,id:int,name:string,subtitle:?string,slug:string,image:?string,url:string,coordinates:?array{lat:?float,lng:?float},label:string}>
     */
    public function featuredSuggestions(int $limit, string $locale): array
    {
        $cities = $this->featuredCities(min(6, $limit), $locale);
        $destinations = $this->featuredDestinations(min(6, $limit), $locale);

        return collect($cities)->concat($destinations)->take($limit)->values()->all();
    }

    /**
     * Admin-curated featured cities with live public counts. Plain arrays
     * are cached (never Eloquent models) with a modest TTL.
     *
     * @return array<int, array{id:int,name:string,slug:string,image:?string,subtitle:?string,property_count:int,tour_count:int,type:string,label:string,url:string}>
     */
    public function featuredCities(int $limit = 8, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $limit = max(1, min(24, $limit));

        return Cache::remember(
            "discovery.featured.cities.{$locale}.{$limit}",
            3600,
            fn (): array => City::query()->active()->featured()->ordered()
                ->with(['state:id,name', 'country:id,name'])
                ->withCount([
                    'properties as property_count' => fn ($query) => $query->where('status', PropertyStatus::Published->value),
                    'packages as tour_count' => fn ($query) => $query->publiclyVisible(),
                ])
                ->take($limit)->get()
                ->map(fn (City $city): array => [
                    'type' => DiscoveryResult::TYPE_CITY,
                    'label' => DiscoveryResult::label(DiscoveryResult::TYPE_CITY),
                    'id' => (int) $city->id,
                    'name' => DiscoveryResult::displayName($city, 'name', $locale) ?? $city->name,
                    'slug' => (string) $city->slug,
                    'image' => $city->image,
                    'subtitle' => $this->citySubtitle($city),
                    'url' => route('cities.show', $city, false),
                    'property_count' => (int) $city->property_count,
                    'tour_count' => (int) $city->tour_count,
                ])->all()
        );
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string,image:?string,subtitle:?string,property_count:int,tour_count:int,type:string,label:string,url:string}>
     */
    public function featuredDestinations(int $limit = 8, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $limit = max(1, min(24, $limit));

        return Cache::remember(
            "discovery.featured.destinations.{$locale}.{$limit}",
            3600,
            fn (): array => Destination::query()->active()->featured()->ordered()
                ->withLocaleTranslations($locale)
                ->with(['city:id,name', 'state:id,name', 'country:id,name'])
                ->withCount([
                    'properties as property_count' => fn ($query) => $query->where('status', PropertyStatus::Published->value),
                    'tourPackages as tour_count' => fn ($query) => $query->publiclyVisible(),
                ])
                ->take($limit)->get()
                ->map(fn (Destination $destination): array => [
                    'type' => DiscoveryResult::TYPE_DESTINATION,
                    'label' => DiscoveryResult::label(DiscoveryResult::TYPE_DESTINATION),
                    'id' => (int) $destination->id,
                    'name' => DiscoveryResult::displayName($destination, 'name', $locale) ?? $destination->name,
                    'slug' => (string) $destination->slug,
                    'image' => $destination->image,
                    'subtitle' => $destination->city?->name ?? $destination->state?->name,
                    'url' => route('destinations.show', $destination, false),
                    'property_count' => (int) $destination->property_count,
                    'tour_count' => (int) $destination->tour_count,
                ])->all()
        );
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string,image:?string,type:string,label:string,url:string}>
     */
    public function featuredProperties(int $limit = 8, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $limit = max(1, min(24, $limit));

        if ($this->modules->isDisabled(ModuleManager::HOTELS)) {
            return [];
        }

        return Property::query()->published()->where('is_featured', true)
            ->with(['city:id,name', 'images'])
            ->orderBy('name')->orderBy('id')
            ->take($limit)->get()
            ->map(function (Property $property) use ($locale): array {
                $primary = $property->images->firstWhere('is_primary', true) ?? $property->images->first();

                return [
                    'type' => DiscoveryResult::TYPE_PROPERTY,
                    'label' => DiscoveryResult::label(DiscoveryResult::TYPE_PROPERTY),
                    'id' => (int) $property->id,
                    'name' => DiscoveryResult::displayName($property, 'name', $locale) ?? $property->name,
                    'slug' => (string) $property->slug,
                    'image' => $primary?->url(),
                    'url' => route('hotels.show', $property->slug, false),
                ];
            })->all();
    }

    /**
     * @return array<int, array{id:int,title:string,slug:string,image:?string,type:string,label:string,url:string}>
     */
    public function featuredTours(int $limit = 8, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $limit = max(1, min(24, $limit));

        if ($this->modules->isDisabled(ModuleManager::TOURS)) {
            return [];
        }

        return TourPackage::query()->publiclyVisible()->where('is_featured', true)
            ->with(['city:id,name'])
            ->orderBy('title')->orderBy('id')
            ->take($limit)->get()
            ->map(fn (TourPackage $tour): array => [
                'type' => DiscoveryResult::TYPE_TOUR,
                'label' => DiscoveryResult::label(DiscoveryResult::TYPE_TOUR),
                'id' => (int) $tour->id,
                'title' => DiscoveryResult::displayName($tour, 'title', $locale) ?? $tour->title,
                'slug' => (string) $tour->slug,
                'image' => $tour->cover_image,
                'url' => route('packages.show', $tour, false),
            ])->all();
    }

    private function citySubtitle(City $city): ?string
    {
        $parts = array_filter([$city->state?->name, $city->country?->name]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
