<?php

namespace App\Services\Discovery;

use App\Enums\PropertyStatus;
use App\Models\City;
use App\Models\Destination;
use App\Models\HotelRatePlan;
use App\Models\Place;
use App\Models\Property;
use App\Models\TourPackage;
use App\Services\MoneyPresenter;
use App\Services\TourBookingPricingService;
use App\Support\Localization;
use App\Support\ModuleManager;
use App\Support\SeoLocalization;
use Illuminate\Support\Collection;

/**
 * City/Destination/Place landing contracts (Phase 13C).
 *
 * Data contracts only — no page design. City and Destination remain
 * distinct entities; Place stays an attraction. Module-disabled
 * collections are omitted (never fake-filled). Landing pages are
 * indexable; arbitrary search/filter result pages are noindex (see
 * Hotel/Tour search seo blocks).
 */
final class DiscoveryLandingService
{
    public function __construct(protected ModuleManager $modules) {}

    /**
     * @return array<string, mixed>
     */
    public function forCity(City $city, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $hotelsOn = $this->modules->isEnabled(ModuleManager::HOTELS);
        $toursOn = $this->modules->isEnabled(ModuleManager::TOURS);

        $city->loadMissing(['state:id,name', 'country:id,name']);
        $city->loadCount([
            'destinations as destinations_count' => fn ($query) => $query->active(),
            'properties as properties_count' => fn ($query) => $query->where('status', PropertyStatus::Published->value),
            'packages as tours_count' => fn ($query) => $query->publiclyVisible(),
        ]);

        $hotels = $hotelsOn
            ? Property::query()->published()->where('city_id', $city->id)
                ->with(['propertyType:id,name', 'city:id,name', 'images'])
                ->orderByDesc('is_featured')->orderBy('name')->orderBy('id')
                ->take(6)->get()
                ->pipe(fn (Collection $properties): array => $this->hotelCards($properties, $locale))
            : [];

        $tours = $toursOn
            ? TourPackage::query()->publiclyVisible()->where('city_id', $city->id)
                ->with(['city:id,name'])->withLocaleTranslations($locale)
                ->orderByDesc('is_featured')->orderBy('title')->orderBy('id')
                ->take(6)->get(['id', 'title', 'slug', 'cover_image', 'duration_days', 'duration_nights', 'price', 'discounted_price'])
                ->pipe(fn (Collection $tourPackages): array => $this->tourCards($tourPackages, $locale))
            : [];

        $destinations = Destination::query()->active()->where('city_id', $city->id)
            ->withLocaleTranslations($locale)
            ->ordered()->take(12)->get(['id', 'name', 'slug', 'image', 'is_featured'])
            ->map(fn (Destination $destination): array => [
                'id' => (int) $destination->id,
                'slug' => (string) $destination->slug,
                'name' => DiscoveryResult::displayName($destination, 'name', $locale) ?? $destination->name,
                'image' => $destination->image,
                'url' => route('destinations.show', $destination, false),
            ])->all();

        $places = Place::query()->active()->whereHas('destination', fn ($inner) => $inner->where('city_id', $city->id))
            ->withLocaleTranslations($locale)
            ->ordered()->take(12)->get(['id', 'name', 'slug', 'image'])
            ->map(fn (Place $place): array => [
                'id' => (int) $place->id,
                'slug' => (string) $place->slug,
                'name' => DiscoveryResult::displayName($place, 'name', $locale) ?? $place->name,
                'image' => $place->image,
                'url' => route('places.show', $place, false),
            ])->all();

        $seo = SeoLocalization::forModel(
            $city,
            'meta_title',
            'meta_description',
            route('cities.show', $city, absolute: true),
            $locale
        );
        $seo['title'] ??= $city->name.' — Travel Guide';

        return [
            'kind' => 'city',
            'id' => (int) $city->id,
            'slug' => (string) $city->slug,
            'name' => DiscoveryResult::displayName($city, 'name', $locale) ?? $city->name,
            'geography' => [
                'state' => $city->state?->name,
                'country' => $city->country?->name,
            ],
            'image' => $city->image,
            'is_featured' => (bool) $city->is_featured,
            'counts' => [
                'destinations' => (int) $city->destinations_count,
                'properties' => $hotelsOn ? (int) $city->properties_count : 0,
                'tours' => $toursOn ? (int) $city->tours_count : 0,
            ],
            'hotels' => $hotels,
            'tours' => $tours,
            'destinations' => $destinations,
            'places' => $places,
            'seo' => $seo + ['noindex' => false],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forDestination(Destination $destination, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $hotelsOn = $this->modules->isEnabled(ModuleManager::HOTELS);
        $toursOn = $this->modules->isEnabled(ModuleManager::TOURS);

        $destination->loadMissing(['city:id,name', 'state:id,name', 'country:id,name', 'translations']);

        $hotels = $hotelsOn
            ? Property::query()->published()
                ->where(fn ($inner) => $inner->where('destination_id', $destination->id)
                    ->when($destination->city_id !== null, fn ($or) => $or->orWhere('city_id', $destination->city_id)))
                ->with(['propertyType:id,name', 'city:id,name', 'images'])
                ->orderByDesc('is_featured')->orderBy('name')->orderBy('id')
                ->take(6)->get()
                ->pipe(fn (Collection $properties): array => $this->hotelCards($properties, $locale))
            : [];

        $tours = $toursOn
            ? TourPackage::query()->publiclyVisible()
                ->whereHas('destinations', fn ($inner) => $inner->where('destinations.id', $destination->id))
                ->with(['city:id,name'])->withLocaleTranslations($locale)
                ->orderByDesc('is_featured')->orderBy('title')->orderBy('id')
                ->take(6)->get(['id', 'title', 'slug', 'cover_image', 'duration_days', 'duration_nights', 'price', 'discounted_price'])
                ->pipe(fn (Collection $tourPackages): array => $this->tourCards($tourPackages, $locale))
            : [];

        $places = Place::query()->active()->where('destination_id', $destination->id)
            ->withLocaleTranslations($locale)
            ->ordered()->take(12)->get(['id', 'name', 'slug', 'image'])
            ->map(fn (Place $place): array => [
                'id' => (int) $place->id,
                'slug' => (string) $place->slug,
                'name' => DiscoveryResult::displayName($place, 'name', $locale) ?? $place->name,
                'image' => $place->image,
                'url' => route('places.show', $place, false),
            ])->all();

        $seo = SeoLocalization::forModel(
            $destination,
            'meta_title',
            'meta_description',
            route('destinations.show', $destination, true),
            $locale
        );
        $seo['title'] ??= ($destination->translated('name', $locale) ?? $destination->name).' — Travel Guide';

        return [
            'kind' => 'destination',
            'id' => (int) $destination->id,
            'slug' => (string) $destination->slug,
            'name' => DiscoveryResult::displayName($destination, 'name', $locale) ?? $destination->name,
            'excerpt' => DiscoveryResult::displayName($destination, 'excerpt', $locale) ?? $destination->excerpt,
            'description' => DiscoveryResult::displayName($destination, 'description', $locale) ?? $destination->description,
            'destination_type' => (string) ($destination->destination_type ?? Destination::TYPE_CITY),
            'geography' => [
                'city' => $destination->city?->name,
                'state' => $destination->state?->name ?? $destination->city?->state?->name,
                'country' => $destination->country?->name,
            ],
            'image' => $destination->image,
            'is_featured' => (bool) $destination->is_featured,
            'hotels' => $hotels,
            'tours' => $tours,
            'places' => $places,
            'seo' => $seo + ['noindex' => false],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forPlace(Place $place, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $toursOn = $this->modules->isEnabled(ModuleManager::TOURS);
        $hotelsOn = $this->modules->isEnabled(ModuleManager::HOTELS);

        $place->loadMissing(['translations', 'destination:id,name,city_id', 'destination.city:id,name', 'destination.translations']);

        $tours = $toursOn
            ? TourPackage::query()->publiclyVisible()
                ->whereHas('places', fn ($inner) => $inner->where('places.id', $place->id))
                ->with(['city:id,name'])->withLocaleTranslations($locale)
                ->orderByDesc('is_featured')->orderBy('title')->orderBy('id')
                ->take(6)->get(['id', 'title', 'slug', 'cover_image', 'duration_days', 'duration_nights', 'price', 'discounted_price'])
                ->pipe(fn (Collection $tourPackages): array => $this->tourCards($tourPackages, $locale))
            : [];

        // Nearby hotels resolve through the place's destination context
        // (no fake proximity math).
        $nearbyHotels = [];

        if ($hotelsOn && $place->destination) {
            $nearbyHotels = Property::query()->published()
                ->where('destination_id', $place->destination->id)
                ->with(['city:id,name', 'images'])
                ->orderByDesc('is_featured')->orderBy('name')->orderBy('id')
                ->take(6)->get();

            $nearbyHotels = $this->hotelCards($nearbyHotels, $locale);
        }

        $seo = SeoLocalization::forModel(
            $place,
            'meta_title',
            'meta_description',
            route('places.show', $place, true),
            $locale
        );
        $seo['title'] ??= (DiscoveryResult::displayName($place, 'name', $locale) ?? (string) $place->name).' — Travel Guide';

        return [
            'kind' => 'place',
            'id' => (int) $place->id,
            'slug' => (string) $place->slug,
            'name' => DiscoveryResult::displayName($place, 'name', $locale) ?? $place->name,
            'excerpt' => DiscoveryResult::displayName($place, 'excerpt', $locale) ?? $place->excerpt,
            'description' => DiscoveryResult::displayName($place, 'description', $locale) ?? $place->description,
            'geography' => [
                'destination' => $place->destination ? DiscoveryResult::displayName($place->destination, 'name', $locale) : null,
                'city' => $place->destination?->city?->name,
            ],
            'image' => $place->image,
            'tours' => $tours,
            'nearby_hotels' => $nearbyHotels,
            'seo' => $seo + ['noindex' => false],
        ];
    }

    /**
     * Starting-price helper shared by landing collections when needed.
     * Presentation only; booking quotes stay authoritative.
     *
     * @return array{amount:string,currency:string,display:array<string,mixed>}|null
     */
    public static function startingPriceForProperty(Property $property): ?array
    {
        $row = HotelRatePlan::query()
            ->where('property_id', $property->id)
            ->where('is_active', true)
            ->orderBy('base_rate')->first(['base_rate', 'currency']);

        if (! $row) {
            return null;
        }

        $amount = number_format((float) $row->base_rate, 2, '.', '');

        return [
            'amount' => $amount,
            'currency' => (string) $row->currency,
            'display' => MoneyPresenter::present($amount, (string) $row->currency),
        ];
    }

    /**
     * Starting price for a tour (authoritative INR + display equivalent).
     *
     * @return array{amount:string,currency:string,display:array<string,mixed>}
     */
    public static function startingPriceForTour(TourPackage $tour): array
    {
        $effective = number_format((float) $tour->effective_price, 2, '.', '');

        return [
            'amount' => $effective,
            'currency' => TourBookingPricingService::DEFAULT_CURRENCY,
            'display' => MoneyPresenter::present($effective, TourBookingPricingService::DEFAULT_CURRENCY),
        ];
    }

    /**
     * @param  Collection<int, Property>  $properties
     * @return array<int, array<string, mixed>>
     */
    private function hotelCards(Collection $properties, string $locale): array
    {
        if ($properties->isEmpty()) {
            return [];
        }

        $plans = HotelRatePlan::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->active()
            ->orderBy('base_rate')
            ->get(['property_id', 'base_rate', 'currency'])
            ->groupBy('property_id');

        return $properties->map(function (Property $property) use ($locale, $plans): array {
            $primary = $property->images->firstWhere('is_primary', true) ?? $property->images->first();
            $plan = $plans->get($property->id)?->first();

            return [
                'id' => (int) $property->id,
                'slug' => (string) $property->slug,
                'name' => DiscoveryResult::displayName($property, 'name', $locale) ?? $property->name,
                'image' => $primary?->url(),
                'property_type' => $property->propertyType?->name,
                'location' => $property->city?->name ?? $property->destination?->name,
                'url' => route('hotels.show', $property->slug, false),
                'display_money' => $plan
                    ? MoneyPresenter::present((string) $plan->base_rate, (string) $plan->currency, null, $locale)
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, TourPackage>  $tourPackages
     * @return array<int, array<string, mixed>>
     */
    private function tourCards(Collection $tourPackages, string $locale): array
    {
        return $tourPackages->map(fn (TourPackage $tour): array => [
            'id' => (int) $tour->id,
            'slug' => (string) $tour->slug,
            'title' => DiscoveryResult::displayName($tour, 'title', $locale) ?? $tour->title,
            'image' => $tour->cover_image,
            'duration_days' => (int) $tour->duration_days,
            'duration_nights' => (int) $tour->duration_nights,
            'destination' => $tour->city?->name,
            'display_money' => self::startingPriceForTour($tour)['display'],
            'url' => route('packages.show', $tour, false),
        ])->values()->all();
    }
}
