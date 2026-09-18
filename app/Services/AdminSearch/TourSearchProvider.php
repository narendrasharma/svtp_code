<?php

namespace App\Services\AdminSearch;

use App\Models\Destination;
use App\Models\Place;
use App\Models\TourPackage;
use App\Models\User;
use App\Support\ModuleManager;

/**
 * Tours catalogue search (packages, destinations, places). Disabled
 * automatically when the tours module is off.
 */
class TourSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'tours';
    }

    public function label(): string
    {
        return 'Tours';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null
            && $user->can('tours.view')
            && app(ModuleManager::class)->isEnabled(ModuleManager::TOURS);
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';
        $results = [];

        $packages = TourPackage::query()
            ->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('slug', 'like', $term);
            })
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'slug']);

        foreach ($packages as $package) {
            $results[] = [
                'type' => 'tour',
                'label' => $package->title,
                'subtitle' => 'Tour package',
                'url' => route('admin.packages.show', $package, absolute: false),
                'icon' => 'bi-map',
            ];
        }

        $remaining = $limit - count($results);

        if ($remaining > 0) {
            $destinations = Destination::query()
                ->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                })
                ->orderBy('name')
                ->limit($remaining)
                ->get(['id', 'name', 'slug']);

            foreach ($destinations as $destination) {
                $results[] = [
                    'type' => 'destination',
                    'label' => $destination->name,
                    'subtitle' => 'Destination',
                    'url' => route('admin.destinations.edit', $destination, absolute: false),
                    'icon' => 'bi-geo-alt',
                ];
            }
        }

        $remaining = $limit - count($results);

        if ($remaining > 0) {
            $places = Place::query()
                ->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                })
                ->orderBy('name')
                ->limit($remaining)
                ->get(['id', 'name', 'slug']);

            foreach ($places as $place) {
                $results[] = [
                    'type' => 'place',
                    'label' => $place->name,
                    'subtitle' => 'Place / attraction',
                    'url' => route('admin.places.edit', $place, absolute: false),
                    'icon' => 'bi-pin-map',
                ];
            }
        }

        return $results;
    }
}
