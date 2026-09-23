<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\Review;
use App\Models\Tag;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Services\MoneyPresenter;
use App\Services\TourAvailabilityService;
use App\Services\TourBookingPricingService;
use App\Support\HotelHtml;
use App\Support\Localization;
use App\Support\SeoLocalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TourPackageController extends Controller
{
    public function index(Request $request): RedirectResponse|InertiaResponse
    {
        if ($request->query() === []) {
            return redirect()->to(route('search.tours', [], false));
        }

        $selectedTags = collect($request->input('tags', []))
            ->filter(fn ($tag): bool => is_string($tag) && $tag !== '')
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $packages = TourPackage::publiclyVisible()
            ->with(['city', 'category'])
            ->when($request->filled('destination_id'), fn ($query) => $query->whereHas(
                'destinations',
                fn ($destinationQuery) => $destinationQuery->whereKey($request->integer('destination_id'))
            ))
            ->when($request->destination, fn ($q) => $q->whereHas(
                'destinations',
                fn ($destinationQuery) => $destinationQuery->where('slug', $request->destination)
            ))
            ->when($request->place, fn ($q) => $q->whereHas(
                'places',
                fn ($placeQuery) => $placeQuery->where('slug', $request->place)
            ))
            ->when($selectedTags, fn ($query) => $query->whereHas(
                'tags',
                fn ($tagQuery) => $tagQuery
                    ->where('is_active', true)
                    ->whereIn('slug', $selectedTags)
            ))
            ->when($request->city, fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $request->city)))
            ->when($request->category, fn ($query) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->active()->where('slug', $request->category)
            ))
            ->when($request->min_price, fn ($q) => $q->where('price', '>=', $request->min_price))
            ->when($request->max_price, fn ($q) => $q->where('price', '<=', $request->max_price))
            ->paginate(9)->withQueryString();

        return Inertia::render('Packages/Index', [
            'packages' => $packages,
            'categories' => TourCategory::active()
                ->whereHas('tourPackages', fn ($query) => $query->publiclyVisible())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon']),
            'places' => Place::query()
                ->whereHas('tourPackages', fn ($query) => $query->publiclyVisible())
                ->with('destination:id,name')
                ->orderBy('name')
                ->get(['id', 'destination_id', 'name', 'slug']),
            'tags' => Tag::query()
                ->where('is_active', true)
                ->whereHas('tourPackages', fn ($query) => $query->publiclyVisible())
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'filters' => [
                ...$request->only(
                    'destination_id', 'destination', 'place', 'city', 'category', 'min_price', 'max_price', 'date',
                    'adults', 'children', 'pickup_address', 'pickup_place_id',
                    'pickup_lat', 'pickup_lng'
                ),
                'tags' => $selectedTags,
            ],
        ]);
    }

    public function show(
        Request $request,
        TourPackage $package,
        TourAvailabilityService $availability,
        TourBookingPricingService $pricing,
    ): InertiaResponse {
        $locale = Localization::currentLocale();

        abort_unless($package->is_active && $package->moderation_status?->value === 'approved', 404);

        $package->load([
            'city:id,name,slug',
            'category:id,name,slug,is_active',
            'destinations' => fn ($query) => $query
                ->where('destinations.is_active', true)
                ->orderBy('destinations.sort_order')
                ->orderBy('destinations.name'),
            'places' => fn ($query) => $query
                ->where('places.is_active', true)
                ->whereHas('destination', fn ($destinationQuery) => $destinationQuery->where('is_active', true))
                ->with('destination:id,name,slug')
                ->orderBy('places.sort_order')
                ->orderBy('places.name'),
            'tags' => fn ($query) => $query->where('tags.is_active', true)->orderBy('tags.name'),
            'translations' => fn ($query) => $query->whereIn('locale', array_values(array_unique([$locale, Localization::defaultLocale()]))),
        ])
            ->loadCount('approvedReviews')
            ->loadAvg('approvedReviews', 'rating');

        $context = $this->travelContext($request);
        $availabilityState = $context['travel_date'] !== null
            ? $availability->check($package, $context['travel_date'])
            : ['bookable' => null, 'reason' => null];
        $quote = $pricing->quote($package, $context['adults'], $context['children']);
        $publicPackage = $this->publicPackage($package, $locale, $quote, $availabilityState, $context);

        $seo = SeoLocalization::forModel(
            $package,
            'meta_title',
            'meta_description',
            route('packages.show', $package, absolute: true),
            $locale,
        );
        $seo['title'] = $seo['title'] ?: $publicPackage['title'];
        $seo['description'] = $seo['description'] ?: Str::limit(strip_tags((string) ($publicPackage['overview'] ?? '')), 155, '');
        $seo['image'] = $this->absoluteMediaUrl($publicPackage['cover_image']);
        $seo['structuredData'] = $this->structuredData($publicPackage, $seo['description'], $seo['image']);

        $reviews = $package->approvedReviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(5)
            ->withQueryString()
            ->through(fn (Review $review): array => [
                'id' => $review->id,
                'reviewer_name' => $review->reviewer_name ?: $review->user?->name ?: 'Guest',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->toIso8601String(),
                // Booking-linked reviews are purchase-verified. No booking
                // reference or private reviewer data is exposed publicly.
                'is_verified_booking' => $review->booking_id !== null,
            ]);

        return Inertia::render('Packages/Show', [
            'package' => $publicPackage,
            'reviews' => $reviews,
            'context' => $context,
            'bookability' => $availabilityState,
            'quote' => [
                ...$quote,
                'total_money' => MoneyPresenter::present($quote['total_amount'], TourBookingPricingService::DEFAULT_CURRENCY),
            ],
            'seo' => $seo,
            // Kept as a small compatibility prop for existing consumers.
            'displayPrice' => MoneyPresenter::present(
                (float) $package->effective_price,
                TourBookingPricingService::DEFAULT_CURRENCY
            ),
        ]);
    }

    /**
     * @return array{travel_date:?string,adults:int,children:int,invalid_date:bool}
     */
    private function travelContext(Request $request): array
    {
        $rawDate = $request->query('travel_date');
        $travelDate = is_string($rawDate) ? trim($rawDate) : null;
        $invalidDate = $travelDate !== null && $travelDate !== '' && $this->parseTravelDate($travelDate) === null;

        return [
            'travel_date' => $invalidDate || $travelDate === '' ? null : $travelDate,
            'adults' => max(1, min(30, (int) $request->query('adults', 1))),
            'children' => max(0, min(30, (int) $request->query('children', 0))),
            'invalid_date' => $invalidDate,
        ];
    }

    private function parseTravelDate(string $date): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array{bookable:bool|string|null,reason:?string}  $availabilityState
     * @param  array{travel_date:?string,adults:int,children:int,invalid_date:bool}  $context
     * @return array<string, mixed>
     */
    private function publicPackage(
        TourPackage $package,
        string $locale,
        array $quote,
        array $availabilityState,
        array $context,
    ): array {
        $title = $package->translated('title', $locale) ?: $package->title;
        $overview = HotelHtml::clean($package->translated('overview', $locale), 24000);
        $places = $package->places->map(fn ($place): array => $this->placeData($place))->values()->all();
        $placesById = collect($places)->keyBy('id');

        $itinerary = collect(is_array($package->day_wise_itinerary) ? $package->day_wise_itinerary : [])
            ->values()
            ->map(function ($day, int $index) use ($placesById): ?array {
                if (! is_array($day)) {
                    return null;
                }

                $dayNumber = is_numeric($day['day'] ?? null) ? (int) $day['day'] : $index + 1;
                $details = collect(['details', 'points', 'highlights', 'activities'])
                    ->flatMap(fn (string $key) => is_array($day[$key] ?? null) ? $day[$key] : [$day[$key] ?? null])
                    ->filter(fn ($item): bool => is_string($item) && trim($item) !== '')
                    ->map(fn (string $item): string => trim(strip_tags($item)))
                    ->values()
                    ->all();
                $placeIds = collect(is_array($day['place_ids'] ?? null) ? $day['place_ids'] : [])
                    ->filter(fn ($id): bool => is_numeric($id))
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->map(fn (int $id) => $placesById->get($id))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'day' => $dayNumber,
                    'title' => is_string($day['title'] ?? null) && trim($day['title']) !== '' ? trim($day['title']) : null,
                    'summary' => is_string($day['description'] ?? null) ? trim(strip_tags($day['description'])) : null,
                    'details' => $details,
                    'image' => is_string($day['image'] ?? null) && trim($day['image']) !== '' ? trim($day['image']) : null,
                    'places' => $placeIds,
                ];
            })
            ->filter()
            ->all();

        $categoryRelation = $package->relationLoaded('category') ? $package->getRelation('category') : null;
        $category = $categoryRelation instanceof TourCategory && $categoryRelation->is_active !== false
            ? [
                'name' => $categoryRelation->name,
                'slug' => $categoryRelation->slug,
            ]
            : (is_string($package->getAttribute('category')) && trim($package->getAttribute('category')) !== ''
                ? ['name' => trim($package->getAttribute('category')), 'slug' => Str::slug($package->getAttribute('category'))]
                : null);

        $displayMoney = MoneyPresenter::present($package->effective_price, TourBookingPricingService::DEFAULT_CURRENCY);

        return [
            'id' => (int) $package->id,
            'slug' => (string) $package->slug,
            'title' => $title,
            'overview' => $overview,
            'cover_image' => $package->cover_image,
            'gallery' => collect([$package->cover_image, ...(is_array($package->gallery) ? $package->gallery : [])])
                ->filter(fn ($image): bool => is_string($image) && trim($image) !== '')
                ->map(fn (string $image): string => trim($image))
                ->unique()
                ->values()
                ->all(),
            'is_featured' => (bool) $package->is_featured,
            'duration_days' => (int) $package->duration_days,
            'duration_nights' => (int) $package->duration_nights,
            'city' => $package->city ? ['name' => $package->city->name, 'slug' => $package->city->slug] : null,
            'destinations' => $package->destinations->map(fn ($destination): array => [
                'id' => (int) $destination->id,
                'name' => $destination->name,
                'slug' => $destination->slug,
                'url' => route('destinations.show', $destination, false),
            ])->values()->all(),
            'category' => $category,
            'tags' => $package->tags->map(fn ($tag): array => ['id' => (int) $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->values()->all(),
            'places' => $places,
            'itinerary' => $itinerary,
            'inclusions' => collect(is_array($package->inclusions) ? $package->inclusions : [])
                ->filter(fn ($item): bool => is_string($item) && trim($item) !== '')
                ->map(fn (string $item): string => trim(strip_tags($item)))
                ->values()->all(),
            'exclusions' => collect(is_array($package->exclusions) ? $package->exclusions : [])
                ->filter(fn ($item): bool => is_string($item) && trim($item) !== '')
                ->map(fn (string $item): string => trim(strip_tags($item)))
                ->values()->all(),
            'starting_price' => [
                'amount' => number_format((float) $package->effective_price, 2, '.', ''),
                'currency' => TourBookingPricingService::DEFAULT_CURRENCY,
            ],
            'display_money' => $displayMoney,
            'adult_price_money' => MoneyPresenter::present($quote['base_price'], TourBookingPricingService::DEFAULT_CURRENCY),
            'child_price_money' => MoneyPresenter::present($quote['child_unit_price'], TourBookingPricingService::DEFAULT_CURRENCY),
            'effective_price' => (float) $package->effective_price,
            'approved_reviews_count' => (int) ($package->approved_reviews_count ?? 0),
            'approved_reviews_avg_rating' => $package->approved_reviews_avg_rating !== null ? (float) $package->approved_reviews_avg_rating : null,
            'bookability' => $availabilityState,
            'selection' => $context,
            'booking_url' => route('booking.form', $package, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function placeData($place): array
    {
        return [
            'id' => (int) $place->id,
            'name' => $place->name,
            'slug' => $place->slug,
            'image' => $place->image,
            'destination' => $place->destination ? [
                'name' => $place->destination->name,
                'slug' => $place->destination->slug,
            ] : null,
            'url' => route('places.show', $place, false),
        ];
    }

    private function absoluteMediaUrl(?string $image): ?string
    {
        if ($image === null || trim($image) === '') {
            return null;
        }

        return preg_match('#^https?://#i', $image) ? $image : url('/'.ltrim($image, '/'));
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function structuredData(array $package, string $description, ?string $image): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => $package['title'],
            'url' => route('packages.show', $package['slug'], absolute: true),
        ];

        if ($description !== '') {
            $schema['description'] = $description;
        }

        if ($image !== null) {
            $schema['image'] = $image;
        }

        if (($package['approved_reviews_count'] ?? 0) > 0 && $package['approved_reviews_avg_rating'] !== null) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $package['approved_reviews_avg_rating'],
                'reviewCount' => $package['approved_reviews_count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return $schema;
    }
}
