<?php

namespace App\Services;

use App\Models\City;
use App\Models\Destination;
use App\Models\HomepageSection;
use App\Models\HotelRatePlan;
use App\Models\Place;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\TourPackage;
use App\Services\Discovery\DiscoveryResult;
use App\Services\Discovery\LocationSearchService;
use App\Support\Localization;
use App\Support\ModuleManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Public Phase 13D homepage composer.
 *
 * The registry controls every handler and entity class used here. Stored
 * configuration can select values and IDs only; it cannot select PHP code,
 * SQL, model classes or Vue components.
 */
final class HomepageService
{
    public function __construct(
        protected HomepageSectionService $registry,
        protected ModuleManager $modules,
        protected LocationSearchService $locations,
    ) {}

    /**
     * @return array{sections:array<int,array<string,mixed>>,locale:string,direction:string}
     */
    public function compose(?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();
        $sections = HomepageSection::query()
            ->merchandising()
            ->active()
            ->with(['items', 'translations'])
            ->ordered()
            ->get();

        $payload = [];

        foreach ($sections as $section) {
            $type = (string) $section->section_type;

            if (! HomepageSectionService::isSupportedType($type)) {
                Log::warning('Unsupported homepage section skipped.', ['homepage_section_id' => $section->id, 'type' => $type]);

                continue;
            }

            $definition = HomepageSectionService::merchandisingDefinition($type);
            $module = $definition['module'] ?? null;

            if ($module !== null && $this->modules->isDisabled($module)) {
                continue;
            }

            $items = match ($type) {
                'hero_search' => [],
                'featured_cities' => $this->cities($section, $locale),
                'featured_destinations' => $this->destinations($section, $locale),
                'featured_hotels' => $this->hotels($section, $locale, false),
                'top_rated_hotels' => $this->hotels($section, $locale, true),
                'featured_tours' => $this->tours($section, $locale),
                'featured_places' => $this->places($section, $locale),
                'custom_cta' => [],
                default => [],
            };

            $payload[] = [
                'key' => (string) $section->section_key,
                'type' => $type,
                'title' => $this->localizedSetting($section, $type, 'title', $locale),
                'subtitle' => $this->localizedSetting($section, $type, 'subtitle', $locale),
                'configuration' => $this->configuration($section, $type, $locale),
                'items' => array_values(array_slice($items, 0, $this->itemLimit($section, $type))),
            ];
        }

        return [
            'sections' => $payload,
            'locale' => $locale,
            'direction' => Localization::direction($locale),
        ];
    }

    /**
     * Bounded admin picker search. Every branch is an explicit entity map.
     *
     * @return array<int, array{entity_type:string,entity_id:int,label:string,context:?string}>
     */
    public function searchManualItems(HomepageSection $section, string $query, string $entityType): array
    {
        $type = (string) $section->section_type;

        if (! HomepageSectionService::isSupportedType($type)
            || ! in_array($entityType, HomepageSectionService::merchandisingEntityTypes($type), true)) {
            abort(422, 'This entity type is not supported for the section.');
        }

        $like = '%'.addcslashes(mb_substr(trim($query), 0, 80), '%_').'%';

        return match ($entityType) {
            'city' => City::query()->active()->where('name', 'like', $like)->orderBy('name')->limit(12)->get(['id', 'name'])->map(fn (City $city): array => [
                'entity_type' => 'city', 'entity_id' => (int) $city->id, 'label' => $city->name, 'context' => null,
            ])->all(),
            'destination' => Destination::query()->active()->withLocaleTranslations()->with('city:id,name')->where('name', 'like', $like)->orderBy('name')->limit(12)->get(['id', 'city_id', 'name'])->map(fn (Destination $destination): array => [
                'entity_type' => 'destination', 'entity_id' => (int) $destination->id, 'label' => DiscoveryResult::displayName($destination, 'name'), 'context' => $destination->city?->name,
            ])->all(),
            'property' => $this->modules->isEnabled(ModuleManager::HOTELS)
                ? Property::query()->published()->where('name', 'like', $like)->with('city:id,name')->orderBy('name')->limit(12)->get(['id', 'city_id', 'name'])->map(fn (Property $property): array => [
                    'entity_type' => 'property', 'entity_id' => (int) $property->id, 'label' => $property->name, 'context' => $property->city?->name,
                ])->all()
                : [],
            'tour' => $this->modules->isEnabled(ModuleManager::TOURS)
                ? TourPackage::query()->publiclyVisible()->where('title', 'like', $like)->with('city:id,name')->orderBy('title')->limit(12)->get(['id', 'city_id', 'title'])->map(fn (TourPackage $tour): array => [
                    'entity_type' => 'tour', 'entity_id' => (int) $tour->id, 'label' => $tour->title, 'context' => $tour->city?->name,
                ])->all()
                : [],
            'place' => Place::query()->active()->where('name', 'like', $like)->with('destination:id,name')->orderBy('name')->limit(12)->get(['id', 'destination_id', 'name'])->map(fn (Place $place): array => [
                'entity_type' => 'place', 'entity_id' => (int) $place->id, 'label' => $place->name, 'context' => $place->destination?->name,
            ])->all(),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function configuration(HomepageSection $section, string $type, string $locale): array
    {
        $settings = HomepageSectionService::mergeMerchandisingSettings($type, $section->settings);

        if ($type === 'hero_search') {
            $tabs = array_values(array_filter([
                $this->modules->isEnabled(ModuleManager::HOTELS) ? 'hotels' : null,
                $this->modules->isEnabled(ModuleManager::TOURS) ? 'tours' : null,
                $this->modules->isEnabled(ModuleManager::TAXI) ? 'taxi' : null,
            ]));
            $default = in_array($settings['default_tab'] ?? null, $tabs, true) ? $settings['default_tab'] : ($tabs[0] ?? null);

            return [
                'tabs' => $tabs,
                'default_tab' => $default,
                'search_contracts' => [
                    'hotels' => ['fields' => ['location', 'check_in', 'check_out', 'rooms', 'adults', 'children']],
                    'tours' => ['fields' => ['location', 'travel_date', 'adults', 'kids']],
                    'taxi' => ['fields' => ['pickup', 'drop', 'date', 'time', 'passengers']],
                ],
            ];
        }

        if ($type === 'custom_cta') {
            return [
                'cta_label' => $this->localizedSetting($section, $type, 'cta_label', $locale),
                'cta_url' => $settings['cta_url'] ?? null,
            ];
        }

        return [
            'source_mode' => in_array($section->source_mode, ['automatic', 'manual'], true) ? $section->source_mode : 'automatic',
            'item_limit' => $this->itemLimit($section, $type),
        ];
    }

    private function itemLimit(HomepageSection $section, string $type): int
    {
        $max = $type === 'hero_search' || $type === 'custom_cta' ? 1 : 24;

        return max(1, min($max, (int) ($section->item_limit ?? 8)));
    }

    private function localizedSetting(HomepageSection $section, string $type, string $field, string $locale): ?string
    {
        $translated = $section->translated($field, $locale);

        if (is_string($translated) && trim($translated) !== '') {
            return $translated;
        }

        $fallback = HomepageSectionService::mergeMerchandisingSettings($type, $section->settings)[$field] ?? null;

        return is_string($fallback) && trim($fallback) !== '' ? $fallback : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function cities(HomepageSection $section, string $locale): array
    {
        $limit = $this->itemLimit($section, 'featured_cities');

        if ($section->source_mode !== 'manual') {
            return $this->locations->featuredCities($limit, $locale);
        }

        $ids = $this->manualIds($section, 'city', $limit);

        return City::query()->active()->whereIn('id', $ids)->get(['id', 'name', 'slug', 'image'])
            ->sortBy(fn (City $city): int => array_search($city->id, $ids, true))
            ->map(fn (City $city): array => [
                'type' => DiscoveryResult::TYPE_CITY,
                'id' => (int) $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'image' => $city->image,
                'subtitle' => null,
                'url' => route('cities.show', $city, false),
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function destinations(HomepageSection $section, string $locale): array
    {
        $limit = $this->itemLimit($section, 'featured_destinations');

        if ($section->source_mode !== 'manual') {
            return $this->locations->featuredDestinations($limit, $locale);
        }

        $ids = $this->manualIds($section, 'destination', $limit);

        return Destination::query()->active()->withLocaleTranslations($locale)->with('city:id,name')->whereIn('id', $ids)->get(['id', 'city_id', 'name', 'slug', 'image'])
            ->sortBy(fn (Destination $destination): int => array_search($destination->id, $ids, true))
            ->map(fn (Destination $destination): array => [
                'type' => DiscoveryResult::TYPE_DESTINATION,
                'id' => (int) $destination->id,
                'name' => DiscoveryResult::displayName($destination, 'name', $locale) ?? $destination->name,
                'slug' => $destination->slug,
                'image' => $destination->image,
                'subtitle' => $destination->city?->name,
                'url' => route('destinations.show', $destination, false),
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function places(HomepageSection $section, string $locale): array
    {
        $limit = $this->itemLimit($section, 'featured_places');
        $query = Place::query()->active()->with(['destination:id,name,city_id', 'destination.city:id,name'])->orderBy('sort_order')->orderBy('name');

        if ($section->source_mode === 'manual') {
            $ids = $this->manualIds($section, 'place', $limit);
            $query->whereIn('places.id', $ids);
        }

        return $query->limit($limit)->get(['id', 'destination_id', 'name', 'slug', 'image'])
            ->when($section->source_mode === 'manual', fn (Collection $items): Collection => $items->sortBy(fn (Place $place): int => array_search($place->id, $this->manualIds($section, 'place', $limit), true)))
            ->map(fn (Place $place): array => [
                'type' => DiscoveryResult::TYPE_PLACE,
                'id' => (int) $place->id,
                'name' => $place->name,
                'slug' => $place->slug,
                'image' => $place->image,
                'destination' => $place->destination?->name,
                'city' => $place->destination?->city?->name,
                'subtitle' => $place->destination?->name,
                'url' => route('places.show', $place, false),
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function hotels(HomepageSection $section, string $locale, bool $topRated): array
    {
        $limit = $this->itemLimit($section, $topRated ? 'top_rated_hotels' : 'featured_hotels');
        $query = Property::query()->published()
            ->with(['propertyType:id,name', 'city:id,name', 'destination:id,name'])
            ->withCount(['reviews as approved_reviews_count' => fn ($inner) => $inner->approved()])
            ->withAvg(['reviews as approved_reviews_avg_rating' => fn ($inner) => $inner->approved()], 'overall_rating');

        if ($topRated) {
            $query->whereHas('reviews', fn ($inner) => $inner->approved())
                ->orderByDesc('approved_reviews_avg_rating')
                ->orderByDesc('approved_reviews_count')
                ->orderBy('properties.id');
        } elseif ($section->source_mode === 'manual') {
            $ids = $this->manualIds($section, 'property', $limit);
            $query->whereIn('properties.id', $ids);
        } else {
            $query->where('is_featured', true)->orderBy('name')->orderBy('properties.id');
        }

        $properties = $query->limit($limit)->get();

        if ($section->source_mode === 'manual' && ! $topRated) {
            $ids = $this->manualIds($section, 'property', $limit);
            $properties = $properties->sortBy(fn (Property $property): int => array_search($property->id, $ids, true))->values();
        }

        $images = PropertyImage::query()->whereIn('property_id', $properties->pluck('id'))
            ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'property_id', 'path', 'is_primary'])->groupBy('property_id')->map->first();
        $prices = $this->startingPrices($properties->pluck('id')->all());

        return $properties->map(fn (Property $property): array => $this->hotelCard($property, $images->get($property->id), $prices[$property->id] ?? null))->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function tours(HomepageSection $section, string $locale): array
    {
        $limit = $this->itemLimit($section, 'featured_tours');
        $query = TourPackage::query()->publiclyVisible()->with(['city:id,name', 'destinations:id,name'])
            ->withCount('approvedReviews')->withAvg('approvedReviews', 'rating');

        if ($section->source_mode === 'manual') {
            $ids = $this->manualIds($section, 'tour', $limit);
            $query->whereIn('tour_packages.id', $ids);
        } else {
            $query->where('is_featured', true)->orderBy('title')->orderBy('id');
        }

        $tours = $query->limit($limit)->get();

        if ($section->source_mode === 'manual') {
            $ids = $this->manualIds($section, 'tour', $limit);
            $tours = $tours->sortBy(fn (TourPackage $tour): int => array_search($tour->id, $ids, true))->values();
        }

        return $tours->map(fn (TourPackage $tour): array => [
            'type' => DiscoveryResult::TYPE_TOUR,
            'id' => (int) $tour->id,
            'slug' => $tour->slug,
            'title' => $tour->title,
            'image' => $tour->cover_image,
            'destination' => $tour->city?->name ?? $tour->destinations->first()?->name,
            'duration_days' => (int) $tour->duration_days,
            'rating_average' => $tour->approved_reviews_avg_rating !== null ? number_format((float) $tour->approved_reviews_avg_rating, 2, '.', '') : null,
            'reviews_count' => (int) ($tour->approved_reviews_count ?? 0),
            'starting_price' => ['amount' => number_format((float) $tour->effective_price, 2, '.', ''), 'currency' => TourBookingPricingService::DEFAULT_CURRENCY],
            'display_money' => MoneyPresenter::present($tour->effective_price, TourBookingPricingService::DEFAULT_CURRENCY),
            'url' => route('packages.show', $tour, false),
        ])->all();
    }

    /** @return array<int, int> */
    private function manualIds(HomepageSection $section, string $entityType, int $limit): array
    {
        return $section->items
            ->where('entity_type', $entityType)
            ->sortBy(['sort_order', 'id'])
            ->pluck('entity_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()->take($limit)->values()->all();
    }

    /** @return array<int, array{amount:string,currency:string}> */
    private function startingPrices(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        return HotelRatePlan::query()->whereIn('property_id', $propertyIds)->where('is_active', true)
            ->selectRaw('property_id, currency, MIN(base_rate) as min_rate')
            ->groupBy('property_id', 'currency')->orderBy('min_rate')->get()
            ->groupBy('property_id')->map(fn (Collection $rows): array => [
                'amount' => number_format((float) $rows->first()->min_rate, 2, '.', ''),
                'currency' => (string) $rows->first()->currency,
            ])->all();
    }

    /** @return array<string, mixed> */
    private function hotelCard(Property $property, ?PropertyImage $image, ?array $starting): array
    {
        return [
            'type' => DiscoveryResult::TYPE_PROPERTY,
            'id' => (int) $property->id,
            'slug' => $property->slug,
            'name' => $property->name,
            'image' => $image?->url(),
            'location' => $property->city?->name ?? $property->destination?->name,
            'city' => $property->city?->name,
            'destination' => $property->destination?->name,
            'property_type' => $property->propertyType?->name,
            'star_rating' => $property->star_rating !== null ? (int) $property->star_rating : null,
            'rating_average' => $property->approved_reviews_avg_rating !== null ? number_format((float) $property->approved_reviews_avg_rating, 2, '.', '') : null,
            'reviews_count' => (int) ($property->approved_reviews_count ?? 0),
            'starting_price' => $starting,
            'display_money' => $starting !== null ? MoneyPresenter::present($starting['amount'], $starting['currency']) : null,
            'url' => route('hotels.show', $property->slug, false),
        ];
    }
}
