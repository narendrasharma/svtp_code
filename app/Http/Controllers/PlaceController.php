<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Services\Discovery\DiscoveryLandingService;
use App\Support\Localization;
use App\Support\ModuleManager;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(): Response
    {
        $locale = Localization::currentLocale();
        $places = Place::query()
            ->active()
            ->whereHas('destination', fn ($query) => $query->active())
            ->with(['destination:id,name,slug,city_id', 'destination.city:id,name', 'destination.translations'])
            ->withLocaleTranslations($locale)
            ->ordered()
            ->take(48)
            ->get()
            ->map(fn (Place $place): array => $this->placeCard($place, $locale))
            ->values()
            ->all();

        return Inertia::render('Static/Places', [
            'places' => $places,
            'seo' => [
                'title' => __('common.places_to_explore', [], $locale),
                'description' => __('common.places_page_description', [], $locale),
                'canonical' => route('places', absolute: true),
            ],
        ]);
    }

    public function show(
        Place $place,
        DiscoveryLandingService $landing,
        ModuleManager $modules,
    ): Response {
        abort_unless($place->is_active && $place->destination?->is_active !== false, 404);

        $toursOn = $modules->isEnabled(ModuleManager::TOURS);
        $place->load([
            'translations',
            'destination:id,name,slug,city_id,is_active',
            'destination.city:id,name,slug',
            'destination.translations',
            'tourPackages' => fn ($query) => $query
                ->when($toursOn, fn ($inner) => $inner->publiclyVisible(), fn ($inner) => $inner->whereRaw('1 = 0'))
                ->with(['city:id,name'])
                ->withLocaleTranslations()
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->orderByDesc('is_featured')
                ->orderBy('title'),
        ]);

        $locale = Localization::currentLocale();
        $landingData = $landing->forPlace($place, $locale);
        $card = $this->placeCard($place, $locale);
        $card['tour_packages'] = $place->tourPackages->map(fn ($tour): array => [
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
        ])->values()->all();

        return Inertia::render('Static/Place', [
            'place' => $card,
            'landing' => $landingData,
            'localizedSeo' => $landingData['seo'],
            'seo' => $landingData['seo'],
        ]);
    }

    /** @return array<string, mixed> */
    private function placeCard(Place $place, string $locale): array
    {
        return [
            'id' => (int) $place->id,
            'name' => $place->name,
            'display_name' => $place->translated('name', $locale) ?? $place->name,
            'slug' => $place->slug,
            'description' => $place->description,
            'display_description' => $place->translated('description', $locale) ?? $place->description,
            'image' => $place->image,
            'meta_title' => $place->meta_title,
            'meta_description' => $place->meta_description,
            'destination' => $place->destination ? [
                'id' => (int) $place->destination->id,
                'name' => $place->destination->translated('name', $locale) ?? $place->destination->name,
                'slug' => $place->destination->slug,
                'city' => $place->destination->city ? [
                    'id' => (int) $place->destination->city->id,
                    'name' => $place->destination->city->name,
                    'slug' => $place->destination->city->slug,
                ] : null,
            ] : null,
            'url' => route('places.show', $place, false),
        ];
    }
}
