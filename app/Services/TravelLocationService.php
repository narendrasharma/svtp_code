<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared geography discovery seam (12B.4.1).
 *
 * One place serves future Popular Cities / Popular Destinations sections
 * and the unified autocomplete: compact cards with query-derived counts
 * (never denormalized), plus a normalized type-tagged search across
 * cities, destinations and places. Taxi raw addresses are out of scope —
 * taxi stays coordinate/address driven.
 *
 * No full frontend autocomplete ships in this phase; this is the
 * server-side foundation Phase 13 Unified Search will consume.
 */
class TravelLocationService
{
    /**
     * Admin-curated featured cities with live listing counts.
     *
     * @return Collection<int, array{id:int,name:string,slug:string,image:?string,subtitle:?string,property_count:int,tour_count:int}>
     */
    public function featuredCities(int $limit = 8): Collection
    {
        return City::active()->featured()->ordered()
            ->with(['state:id,name', 'country:id,name'])
            ->withCount([
                'properties as property_count' => fn ($query) => $query->where('status', PropertyStatus::Published->value),
                'packages as tour_count' => fn ($query) => $query->publiclyVisible(),
            ])
            ->take(max(1, min(24, $limit)))
            ->get()
            ->map(fn (City $city): array => [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'image' => $city->image,
                'subtitle' => $this->citySubtitle($city),
                'property_count' => (int) $city->property_count,
                'tour_count' => (int) $city->tour_count,
            ]);
    }

    /**
     * Admin-curated featured destinations with live listing counts.
     *
     * @return Collection<int, array{id:int,name:string,slug:string,image:?string,subtitle:?string,property_count:int,tour_count:int}>
     */
    public function featuredDestinations(int $limit = 8): Collection
    {
        return Destination::active()->featured()->ordered()
            ->with(['city:id,name', 'state:id,name', 'country:id,name'])
            ->withCount([
                'properties as property_count' => fn ($query) => $query->where('status', PropertyStatus::Published->value),
                'tourPackages as tour_count' => fn ($query) => $query->publiclyVisible(),
            ])
            ->take(max(1, min(24, $limit)))
            ->get()
            ->map(fn (Destination $destination): array => [
                'id' => $destination->id,
                'name' => $destination->name,
                'slug' => $destination->slug,
                'image' => $destination->image,
                'subtitle' => LocationHierarchy::displayName($destination),
                'property_count' => (int) $destination->property_count,
                'tour_count' => (int) $destination->tour_count,
            ]);
    }

    /**
     * Normalized unified search across cities, destinations and places.
     * Each row is type-tagged so the future autocomplete can distinguish
     * them. LIKE-based (works on every driver, no fulltext dependency).
     *
     * @return Collection<int, array{type:string,id:int,name:string,subtitle:?string,slug:string,image:?string}>
     */
    public function search(string $term, int $limit = 8): Collection
    {
        $term = Str::of($term)->squish()->limit(80, '')->toString();

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.$term.'%';
        $take = max(1, min(10, $limit));

        $cities = City::active()->with(['state:id,name', 'country:id,name'])
            ->where('name', 'like', $like)
            ->ordered()
            ->take($take)
            ->get()
            ->map(fn (City $city): array => [
                'type' => 'city',
                'id' => $city->id,
                'name' => $city->name,
                'subtitle' => $this->citySubtitle($city),
                'slug' => $city->slug,
                'image' => $city->image,
            ]);

        $destinations = Destination::active()->with(['city:id,name', 'state:id,name', 'country:id,name'])
            ->where('name', 'like', $like)
            ->ordered()
            ->take($take)
            ->get()
            ->map(fn (Destination $destination): array => [
                'type' => 'destination',
                'id' => $destination->id,
                'name' => $destination->name,
                'subtitle' => LocationHierarchy::displayName($destination),
                'slug' => $destination->slug,
                'image' => $destination->image,
            ]);

        $places = Place::active()->with(['destination:id,name'])
            ->where('name', 'like', $like)
            ->ordered()
            ->take($take)
            ->get()
            ->map(fn (Place $place): array => [
                'type' => 'place',
                'id' => $place->id,
                'name' => $place->name,
                'subtitle' => $place->destination?->name,
                'slug' => $place->slug,
                'image' => $place->image,
            ]);

        return $cities->concat($destinations)->concat($places)->values();
    }

    /**
     * SEO metadata foundation for City/Destination landing pages.
     * Reuses the project's meta_title/meta_description conventions:
     * explicit values win, sensible generated text fills the gaps.
     *
     * @return array{title:string,description:?string,image:?string,kind:string}
     */
    public function seoFor(City|Destination $location): array
    {
        $label = LocationHierarchy::displayName($location) ?? $location->name;
        $kind = $location instanceof City ? 'city' : 'destination';

        $description = $location->meta_description
            ?? ($location instanceof Destination ? $location->description : null)
            ?? "Explore stays, tours and attractions in {$label}.";

        return [
            'title' => $location->meta_title ?: "{$location->name} — Travel Guide",
            'description' => Str::of((string) $description)->stripTags()->squish()->limit(170, '')->toString() ?: null,
            'image' => $location->image,
            'kind' => $kind,
        ];
    }

    protected function citySubtitle(City $city): ?string
    {
        $parts = array_filter([$city->state?->name, $city->country?->name]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
