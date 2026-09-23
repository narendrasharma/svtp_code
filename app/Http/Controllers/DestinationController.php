<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Services\Discovery\DiscoveryLandingService;
use App\Support\Localization;
use App\Support\ModuleManager;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    public function index(ModuleManager $modules): Response
    {
        $toursOn = $modules->isEnabled(ModuleManager::TOURS);
        $locale = Localization::currentLocale();
        $destinations = Destination::query()
            ->with('city:id,name')
            ->withLocaleTranslations($locale)
            ->withCount([
                'places as places_count' => fn ($query) => $query->active(),
                'tourPackages as tour_packages_count' => fn ($query) => $toursOn
                    ? $query->publiclyVisible()
                    : $query->whereRaw('1 = 0'),
            ])
            ->active()
            ->ordered()
            ->get()
            ->map(fn (Destination $destination): array => $this->localizedCard($destination))
            ->values()
            ->all();

        return Inertia::render('Static/Destinations', [
            'destinations' => $destinations,
            'seo' => [
                'title' => __('common.destinations', [], $locale),
                'description' => __('common.destinations_page_description', [], $locale),
                'canonical' => route('destinations', absolute: true),
            ],
        ]);
    }

    public function show(
        Destination $destination,
        DiscoveryLandingService $landing,
        ModuleManager $modules,
    ): Response {
        abort_unless($destination->is_active, 404);
        $toursOn = $modules->isEnabled(ModuleManager::TOURS);

        $destination->load([
            'city:id,name,state_id',
            'city.state:id,name',
            'state:id,name',
            'country:id,name',
            'places' => fn ($query) => $query->active()->ordered()->withLocaleTranslations(),
            'tourPackages' => fn ($query) => $query
                ->when($toursOn, fn ($inner) => $inner->publiclyVisible(), fn ($inner) => $inner->whereRaw('1 = 0'))
                ->with('city:id,name')
                ->withLocaleTranslations()
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->orderByDesc('is_featured')
                ->orderBy('title'),
        ]);

        $locale = Localization::currentLocale();
        $landingData = $landing->forDestination($destination, $locale);
        $card = $this->localizedCard($destination, true);
        $card['places'] = $destination->places->map(fn ($place): array => $this->placeCard($place, $locale))->values()->all();
        $card['tour_packages'] = $destination->tourPackages->map(fn ($tour): array => $this->tourCard($tour, $locale))->values()->all();

        return Inertia::render('Static/Destination', [
            'destination' => $card,
            'landing' => $landingData,
            'localizedSeo' => $landingData['seo'],
            'seo' => $landingData['seo'],
        ]);
    }

    /** @return array<string, mixed> */
    protected function localizedCard(Destination $destination): array
    {
        $locale = Localization::currentLocale();

        return [
            'id' => (int) $destination->id,
            'name' => $destination->name,
            'slug' => $destination->slug,
            'display_name' => $destination->translated('name', $locale) ?? $destination->name,
            'description' => $destination->description,
            'display_description' => $destination->translated('description', $locale) ?? $destination->description,
            'image' => $destination->image,
            'destination_type' => $destination->destination_type,
            'is_featured' => (bool) $destination->is_featured,
            'places_count' => (int) ($destination->places_count ?? 0),
            'tour_packages_count' => (int) ($destination->tour_packages_count ?? 0),
            'meta_title' => $destination->meta_title,
            'meta_description' => $destination->meta_description,
            'display_meta_title' => $destination->translated('meta_title', $locale) ?? $destination->meta_title,
            'display_meta_description' => $destination->translated('meta_description', $locale) ?? $destination->meta_description,
            'locale' => $locale,
            'city' => $destination->city ? [
                'id' => (int) $destination->city->id,
                'name' => $destination->city->name,
                'slug' => $destination->city->slug,
            ] : null,
            'state' => $destination->state?->name ?? $destination->city?->state?->name,
            'country' => $destination->country?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function placeCard(object $place, string $locale): array
    {
        return [
            'id' => (int) $place->id,
            'name' => $place->name,
            'display_name' => $place->translated('name', $locale) ?? $place->name,
            'slug' => $place->slug,
            'description' => $place->translated('description', $locale) ?? $place->description,
            'image' => $place->image,
            'url' => route('places.show', $place, false),
        ];
    }

    /** @return array<string, mixed> */
    private function tourCard(object $tour, string $locale): array
    {
        return [
            'id' => (int) $tour->id,
            'title' => $tour->translated('title', $locale) ?? $tour->title,
            'slug' => $tour->slug,
            'cover_image' => $tour->cover_image,
            'duration_days' => (int) $tour->duration_days,
            'duration_nights' => (int) $tour->duration_nights,
            'price' => $tour->price,
            'discounted_price' => $tour->discounted_price,
            'is_featured' => (bool) $tour->is_featured,
            'city' => $tour->city ? ['name' => $tour->city->name] : null,
        ];
    }
}
