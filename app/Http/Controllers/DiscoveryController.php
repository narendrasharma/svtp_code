<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscoveryLandingRequest;
use App\Http\Requests\DiscoveryLocationRequest;
use App\Http\Requests\HotelSearchRequest;
use App\Http\Requests\TourSearchRequest;
use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Services\Discovery\DiscoveryLandingService;
use App\Services\Discovery\DiscoveryResult;
use App\Services\Discovery\HotelSearchQuery;
use App\Services\Discovery\HotelSearchService;
use App\Services\Discovery\LocationSearchService;
use App\Services\Discovery\TaxiDiscoveryService;
use App\Services\Discovery\TourSearchQuery;
use App\Services\Discovery\TourSearchService;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Shared unified search & discovery endpoints (Phase 13C).
 *
 * Geography is shared and module-independent; Hotel/Tour/Taxi slices
 * gate on ModuleManager inside their services. All URLs are
 * deployment-aware root-relative paths (frontend prefixes the /code
 * base via appUrl); identifiers stay language-neutral so RTL switches
 * never alter entity identity.
 */
class DiscoveryController extends Controller
{
    /**
     * GET /discover/locations — unified autocomplete.
     */
    public function locations(
        DiscoveryLocationRequest $request,
        LocationSearchService $locations
    ): JsonResponse {
        $validated = $request->validated();
        $locale = Localization::currentLocale();
        $limit = (int) ($validated['limit'] ?? config('search.autocomplete_limit', 10));

        if ($request->mode() === 'popular') {
            return response()->json([
                'suggestions' => $locations->featuredSuggestions($limit, $locale),
                'mode' => 'popular',
            ]);
        }

        return response()->json([
            'suggestions' => $locations->autocomplete($validated['q'] ?? '', $locale, $limit),
            'mode' => 'suggest',
        ]);
    }

    /**
     * GET /discover/global — grouped header search (no taxi entities).
     */
    public function global(Request $request, LocationSearchService $locations): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        return response()->json(
            $locations->global($request->input('q', ''), Localization::currentLocale())
        );
    }

    /**
     * GET /discover/location — City/Destination/Place landing contract.
     */
    public function location(
        DiscoveryLandingRequest $request,
        DiscoveryLandingService $landing
    ): JsonResponse {
        $validated = $request->validated();
        $locale = Localization::currentLocale();

        $contract = match ($validated['type']) {
            'city' => $this->cityContract((int) $validated['id'], $landing, $locale),
            'destination' => $this->destinationContract((int) $validated['id'], $landing, $locale),
            default => $this->placeContract((int) $validated['id'], $landing, $locale),
        };

        if ($contract === null) {
            abort(404);
        }

        return response()->json($contract);
    }

    /**
     * GET /search/hotels — Hotel search contract.
     */
    public function hotels(
        HotelSearchRequest $request,
        HotelSearchService $hotels
    ): JsonResponse|InertiaResponse {
        if (! $hotels->available()) {
            abort(404);
        }

        $validated = $request->validated();
        $query = HotelSearchQuery::fromArray($validated);
        $results = $hotels->search($query, Localization::currentLocale());

        if (! $request->expectsJson() || $request->header('X-Inertia') === 'true') {
            $location = $this->hotelSearchLocation($validated);
            $title = $location['label'] !== null
                ? __('common.stays_near', ['location' => $location['label']])
                : __('common.hotel_results');

            return Inertia::render('Hotels/Search', [
                'results' => $results,
                'search' => $this->hotelSearchState($validated),
                'location' => $location,
                'seo' => [
                    'title' => $title,
                    'description' => __('common.hotel_results_description'),
                    'canonical' => route('search.hotels', [], absolute: true),
                    'noindex' => true,
                ],
            ]);
        }

        return response()->json($results);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function hotelSearchState(array $validated): array
    {
        return [
            'q' => $validated['q'] ?? null,
            'location_type' => $validated['location_type'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'check_in' => $validated['check_in'] ?? null,
            'check_out' => $validated['check_out'] ?? null,
            'rooms' => (int) ($validated['rooms'] ?? 1),
            'adults' => (int) ($validated['adults'] ?? 2),
            'children' => (int) ($validated['children'] ?? 0),
            'property_type_id' => $validated['property_type_id'] ?? null,
            'star_rating' => $validated['star_rating'] ?? null,
            'amenities' => array_values($validated['amenities'] ?? []),
            'meal_plan' => $validated['meal_plan'] ?? null,
            'cancellation_mode' => $validated['cancellation_mode'] ?? null,
            'min_price' => $validated['min_price'] ?? null,
            'max_price' => $validated['max_price'] ?? null,
            'price_currency' => strtoupper((string) ($validated['price_currency'] ?? 'USD')),
            'min_rating' => $validated['min_rating'] ?? null,
            'sort' => $validated['sort'] ?? 'recommended',
            'page' => (int) ($validated['page'] ?? 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{type:?string,id:?int,label:?string}
     */
    private function hotelSearchLocation(array $validated): array
    {
        $type = $validated['location_type'] ?? null;
        $id = isset($validated['location_id']) ? (int) $validated['location_id'] : null;

        if ($type === null || $id === null) {
            return [
                'type' => null,
                'id' => null,
                'label' => isset($validated['q']) ? trim((string) $validated['q']) : null,
            ];
        }

        $model = match ($type) {
            'city' => City::query()->active()->find($id),
            'destination' => Destination::query()->active()->find($id),
            'place' => Place::query()->active()->find($id),
            default => null,
        };

        return [
            'type' => $type,
            'id' => $id,
            'label' => $model ? DiscoveryResult::displayName($model, 'name', Localization::currentLocale()) : null,
        ];
    }

    /**
     * GET /search/tours — Tour search contract.
     */
    public function tours(
        TourSearchRequest $request,
        TourSearchService $tours
    ): JsonResponse|InertiaResponse {
        if (! $tours->available()) {
            if ($request->expectsJson() || $request->header('X-Inertia') === 'true') {
                return response()->json(['message' => 'Tours are currently unavailable.'], 404);
            }

            abort(404);
        }

        $validated = $request->validated();
        $query = TourSearchQuery::fromArray($validated);
        $results = $tours->search($query, Localization::currentLocale());

        if (! $request->expectsJson() || $request->header('X-Inertia') === 'true') {
            $location = $this->tourSearchLocation($validated);
            $title = $location['label'] !== null
                ? __('common.tours_in', ['location' => $location['label']])
                : ($validated['q'] ?? null
                    ? __('common.tours_matching').' “'.trim((string) $validated['q']).'”'
                    : __('common.tour_results'));

            return Inertia::render('Tours/Search', [
                'results' => $results,
                'search' => $this->tourSearchState($validated),
                'location' => $location,
                'seo' => [
                    'title' => $title,
                    'description' => __('common.tour_results_description'),
                    'canonical' => route('search.tours', [], absolute: true),
                    'noindex' => true,
                ],
            ]);
        }

        return response()->json($results);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function tourSearchState(array $validated): array
    {
        return [
            'q' => $validated['q'] ?? null,
            'location_type' => $validated['location_type'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'travel_date' => $validated['travel_date'] ?? null,
            'adults' => (int) ($validated['adults'] ?? 1),
            'children' => (int) ($validated['children'] ?? 0),
            'category_id' => $validated['category_id'] ?? null,
            'min_duration' => $validated['min_duration'] ?? null,
            'max_duration' => $validated['max_duration'] ?? null,
            'min_price' => $validated['min_price'] ?? null,
            'max_price' => $validated['max_price'] ?? null,
            'tags' => array_values($validated['tags'] ?? []),
            'featured' => (bool) ($validated['featured'] ?? false),
            'min_rating' => $validated['min_rating'] ?? null,
            'sort' => $validated['sort'] ?? 'recommended',
            'page' => (int) ($validated['page'] ?? 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{type:?string,id:?int,label:?string}
     */
    private function tourSearchLocation(array $validated): array
    {
        $type = $validated['location_type'] ?? null;
        $id = isset($validated['location_id']) ? (int) $validated['location_id'] : null;

        if ($type === null || $id === null) {
            return [
                'type' => null,
                'id' => null,
                'label' => isset($validated['q']) ? trim((string) $validated['q']) : null,
            ];
        }

        $model = match ($type) {
            'city' => City::query()->active()->find($id),
            'destination' => Destination::query()->active()->find($id),
            'place' => Place::query()->active()->find($id),
            default => null,
        };

        return [
            'type' => $type,
            'id' => $id,
            'label' => $model ? DiscoveryResult::displayName($model, 'name', Localization::currentLocale()) : null,
        ];
    }

    /**
     * GET /discover/taxi — Taxi discovery contract (operational model
     * preserved; suggestions are discovery hints only).
     */
    public function taxi(Request $request, TaxiDiscoveryService $taxi): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        if (! $taxi->available()) {
            return response()->json(['message' => 'Taxi is currently unavailable.'], 404);
        }

        return response()->json(
            $taxi->contract($request->input('q', ''), Localization::currentLocale())
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cityContract(int $id, DiscoveryLandingService $landing, string $locale): ?array
    {
        $city = City::query()->active()->find($id);

        return $city ? $landing->forCity($city, $locale) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function destinationContract(int $id, DiscoveryLandingService $landing, string $locale): ?array
    {
        $destination = Destination::query()->active()->find($id);

        return $destination ? $landing->forDestination($destination, $locale) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function placeContract(int $id, DiscoveryLandingService $landing, string $locale): ?array
    {
        $place = Place::query()->active()->find($id);

        return $place ? $landing->forPlace($place, $locale) : null;
    }
}
