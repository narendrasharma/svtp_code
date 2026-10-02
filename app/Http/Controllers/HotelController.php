<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\HotelRatePlan;
use App\Models\HotelReview;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\PropertyType;
use App\Services\HotelAvailabilityService;
use App\Services\HotelBookingService;
use App\Services\HotelCustomFieldService;
use App\Services\HotelPricingService;
use App\Services\HotelRatingSummaryService;
use App\Services\HotelReviewService;
use App\Services\MoneyPresenter;
use App\Support\HotelSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public hotel browsing (12B.1).
 *
 * Published properties only. No availability or pricing in this phase —
 * the CTA is "View Property". Internal vendor/account data never leaves
 * the server; contact details honour the public-contact setting.
 */
class HotelController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'city_id', 'property_type_id', 'star_rating']);

        $query = Property::published()
            ->with(['propertyType:id,name,slug', 'city:id,name', 'images'])
            ->orderByDesc('is_featured')
            ->latest('published_at');

        if (! empty($filters['search'])) {
            $search = '%'.mb_substr(trim((string) $filters['search']), 0, 80).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('address_line_1', 'like', $search));
        }

        if (! empty($filters['city_id'])) {
            $query->where('city_id', (int) $filters['city_id']);
        }

        if (! empty($filters['property_type_id'])) {
            $query->where('property_type_id', (int) $filters['property_type_id']);
        }

        if (! empty($filters['star_rating']) && in_array((int) $filters['star_rating'], [1, 2, 3, 4, 5], true)) {
            $query->where('star_rating', (int) $filters['star_rating']);
        }

        $properties = $query->paginate(HotelSettings::perPage())->withQueryString();
        $properties->getCollection()->transform(fn (Property $property): array => $this->cardFor($property));

        return Inertia::render('Hotels/Index', [
            'properties' => $properties,
            'filters' => $filters,
            'types' => PropertyType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'cities' => City::whereHas('properties', fn ($q) => $q->published())->orderBy('name')->limit(100)->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, string $slug, HotelReviewService $reviews, HotelRatingSummaryService $summaries): Response
    {
        $filters = $request->validate([
            'review_sort' => ['sometimes', 'in:recent,highest,lowest'],
            'reviews_page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'check_in' => ['sometimes', 'date_format:Y-m-d', 'required_with:check_out'],
            'check_out' => ['sometimes', 'date_format:Y-m-d', 'required_with:check_in', 'after:check_in'],
            'rooms' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ]);
        $property = Property::published()
            ->with(['propertyType:id,name', 'city:id,name', 'state:id,name', 'destination:id,name', 'amenities' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'), 'images'])
            ->where('slug', $slug)
            ->firstOrFail();

        $property->load(['roomTypes' => fn ($q) => $q->active()->with([
            'bedTypes' => fn ($b) => $b->where('hotel_bed_types.is_active', true)->orderBy('hotel_bed_types.sort_order'),
            'amenities' => fn ($a) => $a->where('hotel_amenities.is_active', true)->orderBy('hotel_amenities.sort_order'),
            'images',
        ])->orderBy('sort_order')->orderBy('id')]);

        $summary = $reviews->enabled() ? $summaries->forProperty($property->id) : null;
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LodgingBusiness',
            'name' => $property->name,
            'url' => route('hotels.show', $property->slug),
        ];

        if ($summary !== null && $summary['reviews_count'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating', 'ratingValue' => $summary['rating_average'],
                'reviewCount' => $summary['reviews_count'], 'bestRating' => 5, 'worstRating' => 1,
            ];
        }

        return Inertia::render('Hotels/Show', [
            'property' => $this->detailFor($property),
            'maxRoomsPerBooking' => max(1, (int) HotelSettings::get('hotel.booking.max_rooms_per_booking')),
            'reviewSummary' => $summary,
            'reviews' => fn () => $summary !== null ? $reviews->publicReviews($property, $filters['review_sort'] ?? 'recent') : null,
            'reviewCategories' => $summary !== null ? HotelReview::CATEGORY_RATINGS : [],
            'reviewSort' => $filters['review_sort'] ?? 'recent',
            'stay' => [
                'check_in' => $filters['check_in'] ?? null,
                'check_out' => $filters['check_out'] ?? null,
                'rooms' => (int) ($filters['rooms'] ?? 1),
                'adults' => (int) ($filters['adults'] ?? 2),
                'children' => (int) ($filters['children'] ?? 0),
            ],
            'seo' => [
                'title' => $property->meta_title ?: $property->name,
                'description' => $property->meta_description ?: $property->short_description,
                'image' => $property->primaryImage()?->url(),
                'canonical' => route('hotels.show', $property->slug, absolute: true),
                'structuredData' => $schema,
            ],
        ]);
    }

    /**
     * Public-safe stay availability (12B.3, inventory only — no pricing).
     *
     * Minimal summary per room type: availability flags and per-night
     * availability only. Internal notes, unit identifiers, blocked
     * counts and vendor data never leave the server.
     */
    public function availability(Request $request, string $slug): JsonResponse
    {
        $availability = app(HotelAvailabilityService::class);

        if (! $availability->publicCheckEnabled()) {
            abort(404);
        }

        $property = Property::published()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d'],
            'rooms' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'room_type_id' => ['sometimes', 'integer'],
        ]);

        $rooms = (int) ($data['rooms'] ?? 1);

        if ($data['check_in'] < $availability->propertyToday($property)->toDateString()) {
            return response()->json(['message' => 'Check-in cannot be in the past.'], 422);
        }

        if (! empty($data['room_type_id'])) {
            $roomType = HotelRoomType::where('property_id', $property->id)
                ->active()
                ->findOrFail((int) $data['room_type_id']);

            $checks = [$availability->checkRoomType($roomType, $data['check_in'], $data['check_out'], $rooms)];
        } else {
            $checks = $availability->availableRoomTypes($property, $data['check_in'], $data['check_out'], $rooms);
        }

        $summaries = [];

        foreach ($checks as $check) {
            $roomType = HotelRoomType::find($check['room_type_id']);

            if (! $roomType) {
                continue;
            }

            $summaries[] = [
                'slug' => $roomType->slug,
                'name' => $roomType->name,
                'max_occupancy' => $roomType->max_occupancy,
                'available' => $check['available'],
                'available_rooms' => $check['min_available_rooms'],
                'nights' => array_map(fn (array $night): array => [
                    'date' => $night['date'],
                    'available' => $night['available'],
                ], $check['nights']),
            ];
        }

        return response()->json([
            'property' => ['name' => $property->name, 'slug' => $property->slug],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'requested_rooms' => $rooms,
            'available' => collect($summaries)->contains('available', true),
            'room_types' => $summaries,
        ]);
    }

    /**
     * Public-safe stay rates (12B.4, pricing only — no booking).
     *
     * Per active room: inventory-gated availability plus every active
     * rate plan with its server-authoritative nightly breakdown and
     * totals. Browser totals are never accepted anywhere downstream.
     */
    public function rates(Request $request, string $slug): JsonResponse
    {
        $pricing = app(HotelPricingService::class);
        $availability = app(HotelAvailabilityService::class);

        if (! $availability->publicCheckEnabled()) {
            abort(404);
        }

        $property = Property::published()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d'],
            'rooms' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'room_type_id' => ['sometimes', 'integer'],
        ]);

        $rooms = (int) ($data['rooms'] ?? 1);
        $adults = (int) ($data['adults'] ?? 2);
        $children = (int) ($data['children'] ?? 0);

        if ($data['check_in'] < $availability->propertyToday($property)->toDateString()) {
            return response()->json(['message' => 'Check-in cannot be in the past.'], 422);
        }

        $roomQuery = HotelRoomType::where('property_id', $property->id)->active();

        if (! empty($data['room_type_id'])) {
            $roomQuery->whereKey((int) $data['room_type_id']);
        }

        $roomTypes = $roomQuery->orderBy('sort_order')->orderBy('id')->get();

        if (! empty($data['room_type_id']) && $roomTypes->isEmpty()) {
            abort(404);
        }

        $inventory = collect($availability->availableRoomTypes($property, $data['check_in'], $data['check_out'], $rooms))
            ->keyBy('room_type_id');

        $roomsOut = [];

        foreach ($roomTypes as $roomType) {
            $stock = $inventory->get($roomType->id);
            $plansOut = [];

            foreach ($pricing->quotesForRoom($roomType, $data['check_in'], $data['check_out'], $rooms, $adults, $children, false) as $quote) {
                $plan = HotelRatePlan::find($quote['rate_plan_id']);

                if (! $plan) {
                    continue;
                }

                $plansOut[] = [
                    'code' => $plan->code,
                    'room_type_id' => $roomType->id,
                    'rate_plan_id' => $plan->id,
                    'name' => $plan->name,
                    'meal_plan' => $plan->meal_plan,
                    'cancellation_mode' => $plan->cancellation_mode,
                    'currency' => $quote['currency'],
                    'available' => $quote['available'] && ($stock['available'] ?? false),
                    'unavailable_reason' => $quote['available'] ? ($stock['available'] ?? false ? null : 'Not enough rooms available for the selected dates.') : $quote['unavailable_reason'],
                    'nights_count' => $quote['nights_count'],
                    'nightly' => $quote['nightly'],
                    'subtotal' => $quote['subtotal'],
                    'taxes' => $quote['taxes'],
                    'fees' => $quote['fees'],
                    'total' => $quote['total'],
                    'display_subtotal' => MoneyPresenter::present($quote['subtotal'], $quote['currency']),
                    'display_taxes' => MoneyPresenter::present($this->chargeTotal($quote['taxes']), $quote['currency']),
                    'display_fees' => MoneyPresenter::present($this->chargeTotal($quote['fees']), $quote['currency']),
                    // Phase 13B representative display integration: the
                    // authoritative total/currency above are untouched
                    // (fingerprint covers them); display_total is for
                    // visitor presentation in the selected currency only.
                    'display_total' => MoneyPresenter::present($quote['total'], $quote['currency']),
                    'quote_fingerprint' => HotelBookingService::fingerprint($quote),
                ];
            }

            $roomsOut[] = [
                'slug' => $roomType->slug,
                'name' => $roomType->name,
                'max_occupancy' => $roomType->max_occupancy,
                'available' => (bool) ($stock['available'] ?? false),
                'available_rooms' => $stock['min_available_rooms'] ?? 0,
                'plans' => $plansOut,
            ];
        }

        return response()->json([
            'property' => ['name' => $property->name, 'slug' => $property->slug],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'requested_rooms' => $rooms,
            'adults' => $adults,
            'children' => $children,
            'available' => collect($roomsOut)->contains('available', true),
            'room_types' => $roomsOut,
        ]);
    }

    /**
     * @param  array<int, array{name: string, amount: string}>  $charges
     */
    protected function chargeTotal(array $charges): string
    {
        return array_reduce($charges, fn (string $total, array $charge): string => bcadd($total, (string) $charge['amount'], 2), '0.00');
    }

    /**
     * @return array<string, mixed>
     */
    protected function cardFor(Property $property): array
    {
        $primary = $property->images->firstWhere('is_primary', true) ?? $property->images->first();

        return [
            'name' => $property->name,
            'slug' => $property->slug,
            'type' => $property->propertyType?->name,
            'city' => $property->city?->name,
            'destination' => $property->destination?->name,
            'country_code' => $property->country_code,
            'star_rating' => $property->star_rating,
            'short_description' => $property->short_description,
            'image' => $primary?->url(),
            'amenity_count' => $property->amenities()->where('is_active', true)->count(),
            'reviews_count' => HotelSettings::enabled('hotel.reviews.enabled') ? (int) $property->reviews_count : 0,
            'rating_average' => HotelSettings::enabled('hotel.reviews.enabled') ? $property->rating_average : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailFor(Property $property): array
    {
        $showContact = HotelSettings::enabled('hotel.show_contact_details');

        return [
            'name' => $property->name,
            'slug' => $property->slug,
            'type' => $property->propertyType?->name,
            'star_rating' => $property->star_rating,
            'short_description' => $property->short_description,
            'description' => $property->description,
            'address_line_1' => $property->address_line_1,
            'address_line_2' => $property->address_line_2,
            'city' => $property->city?->name,
            'state' => $property->state?->name,
            'country_code' => $property->country_code,
            'postal_code' => $property->postal_code,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'phone' => $showContact ? $property->phone : null,
            'email' => $showContact ? $property->email : null,
            'website' => $property->website,
            'check_in_time' => $property->check_in_time?->format('H:i'),
            'check_out_time' => $property->check_out_time?->format('H:i'),
            'timezone' => $property->timezone,
            'currency' => $property->currency,
            'children_policy' => $property->children_policy,
            'pet_policy' => $property->pet_policy,
            'smoking_policy' => $property->smoking_policy,
            'check_in_instructions' => $property->check_in_instructions,
            'house_rules' => $property->house_rules,
            'amenities' => $property->amenities->map(fn ($amenity): array => [
                'name' => $amenity->name,
                'icon' => $amenity->icon,
                'category' => $amenity->category,
            ])->all(),
            'gallery' => $property->images->map(fn ($image): array => [
                'url' => $image->url(),
                'alt' => $image->alt_text,
                'primary' => (bool) $image->is_primary,
            ])->all(),
            'rooms' => $property->relationLoaded('roomTypes')
                ? $property->roomTypes->map(fn ($room): array => $this->roomFor($room))->all()
                : [],
            'customFields' => app(HotelCustomFieldService::class)->publicValues('property', $property->id),
        ];
    }

    /**
     * Public-safe room card: presentation only. Physical units and their
     * internal notes are never exposed; no pricing or availability yet.
     *
     * @param  HotelRoomType  $room
     * @return array<string, mixed>
     */
    protected function roomFor($room): array
    {
        $showSize = HotelSettings::enabled('hotel.rooms.show_size');
        $showBeds = HotelSettings::enabled('hotel.rooms.show_bed_details');
        $primary = $room->images->firstWhere('is_primary', true) ?? $room->images->first();

        return [
            'name' => $room->name,
            'slug' => $room->slug,
            'short_description' => $room->short_description,
            'description' => $room->description,
            'max_adults' => $room->max_adults,
            'max_children' => $room->max_children,
            'max_occupancy' => $room->max_occupancy,
            'size' => $showSize && $room->size_value !== null
                ? trim((string) $room->size_value.' '.($room->size_unit ?? '')) : null,
            'beds' => $showBeds ? ($room->bed_summary ?? $this->fallbackBedSummary($room)) : null,
            'amenities' => $room->amenities->map(fn ($amenity): array => [
                'name' => $amenity->name,
                'icon' => $amenity->icon,
            ])->all(),
            'image' => $primary?->url(),
            'gallery' => $room->images->map(fn ($image): array => [
                'url' => $image->url(),
                'alt' => $image->alt_text,
            ])->all(),
            'customFields' => app(HotelCustomFieldService::class)->publicValues('room_type', $room->id),
        ];
    }

    /**
     * @param  HotelRoomType  $room
     */
    protected function fallbackBedSummary($room): ?string
    {
        $parts = $room->bedTypes->map(fn ($bed): string => $bed->pivot->quantity.' × '.$bed->name)->all();

        return $parts === [] ? null : implode(', ', $parts);
    }
}
