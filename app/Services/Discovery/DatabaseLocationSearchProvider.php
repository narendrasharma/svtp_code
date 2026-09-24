<?php

namespace App\Services\Discovery;

use App\Contracts\Discovery\SearchProvider;
use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\Property;
use App\Models\TourPackage;
use App\Support\Localization;
use App\Support\ModuleManager;
use Illuminate\Support\Collection;

/**
 * Default database location provider (Phase 13C).
 *
 * Bounded LIKE queries per entity type (works on every driver, no
 * fulltext dependency). Ranking is deterministic: exact → prefix →
 * contains → featured → sort_order → stable id. Public visibility
 * scopes are always applied; Hotels/Tours slices respect module gating
 * while City/Destination/Place stay shared.
 */
final class DatabaseLocationSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'database';
    }

    public function suggest(string $term, int $limit, ?string $locale = null): Collection
    {
        $locale ??= Localization::currentLocale();
        $term = SearchTerm::normalize($term);

        if (! SearchTerm::meetsMinimum($term)) {
            return collect();
        }

        $take = max(1, min(10, $limit));
        $like = SearchTerm::like($term);
        $modules = app(ModuleManager::class);
        $hotelsOn = $modules->isEnabled(ModuleManager::HOTELS);
        $toursOn = $modules->isEnabled(ModuleManager::TOURS);

        $cities = $this->cities($like, $take, $locale);
        $destinations = $this->destinations($like, $take, $locale);
        $places = $this->places($like, $take, $locale);
        $properties = $hotelsOn ? $this->properties($like, $take, $locale) : collect();
        $tours = $toursOn ? $this->tours($like, $take, $locale) : collect();

        return $cities->concat($destinations)
            ->concat($places)
            ->concat($properties)
            ->concat($tours)
            ->map(fn (array $row): array => $row + ['_rank' => SearchTerm::rankTier((string) ($row['name'] ?? ''), $term)])
            ->sortBy(fn (array $row): array => [$row['_rank'], $row['sort_key'] ?? '', $row['id']])
            ->take($take)
            ->map(fn (array $row): array => collect($row)->except(['_rank', 'sort_key'])->all())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cities(string $like, int $take, string $locale): Collection
    {
        return City::query()->active()
            ->with(['state:id,name', 'country:id,name'])
            ->where('name', 'like', $like)
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->take($take)->get()
            ->map(fn (City $city): array => DiscoveryResult::make(
                DiscoveryResult::TYPE_CITY,
                (int) $city->id,
                DiscoveryResult::displayName($city, 'name', $locale) ?? $city->name,
                $this->citySubtitle($city),
                (string) $city->slug,
                $city->image,
                route('cities.show', $city, false),
                $this->coordinates($city->latitude ?? null, $city->longitude ?? null),
            ) + ['sort_key' => ($city->is_featured ? '0' : '1').'|'.$city->sort_order.'|'.$city->name]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function destinations(string $like, int $take, string $locale): Collection
    {
        return Destination::query()->active()
            ->withLocaleTranslations($locale)
            ->with(['city:id,name', 'state:id,name', 'country:id,name'])
            ->where('name', 'like', $like)
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->take($take)->get()
            ->map(function (Destination $destination) use ($locale): array {
                $city = $destination->city?->name;
                $subtitle = $city && $city !== $destination->name
                    ? 'Near '.$city
                    : ($destination->state?->name ?? $destination->country?->name);

                return DiscoveryResult::make(
                    DiscoveryResult::TYPE_DESTINATION,
                    (int) $destination->id,
                    DiscoveryResult::displayName($destination, 'name', $locale) ?? $destination->name,
                    $subtitle,
                    (string) $destination->slug,
                    $destination->image,
                    route('destinations.show', $destination, false),
                    $this->coordinates($destination->latitude ?? null, $destination->longitude ?? null),
                ) + ['sort_key' => ($destination->is_featured ? '0' : '1').'|'.$destination->sort_order.'|'.$destination->name];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function places(string $like, int $take, string $locale): Collection
    {
        return Place::query()->active()
            ->with(['destination:id,name'])
            ->where('name', 'like', $like)
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->take($take)->get()
            ->map(fn (Place $place): array => DiscoveryResult::make(
                DiscoveryResult::TYPE_PLACE,
                (int) $place->id,
                DiscoveryResult::displayName($place, 'name', $locale) ?? $place->name,
                $place->destination?->name,
                (string) $place->slug,
                $place->image,
                route('places.show', $place, false),
                $this->coordinates($place->latitude ?? null, $place->longitude ?? null),
            ) + ['sort_key' => '1|'.$place->sort_order.'|'.$place->name]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function properties(string $like, int $take, string $locale): Collection
    {
        return Property::query()->published()
            ->with(['city:id,name', 'destination:id,name', 'images'])
            ->where('name', 'like', $like)
            ->orderByDesc('is_featured')->orderBy('name')->orderBy('id')
            ->take($take)->get()
            ->map(function (Property $property) use ($locale): array {
                $primary = $property->images->firstWhere('is_primary', true) ?? $property->images->first();

                return DiscoveryResult::make(
                    DiscoveryResult::TYPE_PROPERTY,
                    (int) $property->id,
                    DiscoveryResult::displayName($property, 'name', $locale) ?? $property->name,
                    $property->city?->name ?? $property->destination?->name,
                    (string) $property->slug,
                    $primary?->url(),
                    route('hotels.show', $property->slug, false),
                    $this->coordinates($property->latitude ?? null, $property->longitude ?? null),
                ) + ['sort_key' => ($property->is_featured ? '0' : '1').'|'.$property->name];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tours(string $like, int $take, string $locale): Collection
    {
        return TourPackage::query()->publiclyVisible()
            ->with(['city:id,name'])
            ->where('title', 'like', $like)
            ->orderByDesc('is_featured')->orderBy('title')->orderBy('id')
            ->take($take)->get()
            ->map(fn (TourPackage $tour): array => DiscoveryResult::make(
                DiscoveryResult::TYPE_TOUR,
                (int) $tour->id,
                DiscoveryResult::displayName($tour, 'title', $locale) ?? $tour->title,
                $tour->city?->name,
                (string) $tour->slug,
                $tour->cover_image,
                route('packages.show', $tour, false),
                null,
            ) + ['sort_key' => ($tour->is_featured ? '0' : '1').'|'.$tour->title]);
    }

    private function citySubtitle(City $city): ?string
    {
        $parts = array_filter([$city->state?->name, $city->country?->name]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @return array{lat:?float,lng:?float}|null
     */
    private function coordinates(mixed $lat, mixed $lng): ?array
    {
        if ($lat === null && $lng === null) {
            return null;
        }

        return [
            'lat' => $lat !== null ? (float) $lat : null,
            'lng' => $lng !== null ? (float) $lng : null,
        ];
    }
}
