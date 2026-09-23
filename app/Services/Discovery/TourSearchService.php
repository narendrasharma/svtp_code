<?php

namespace App\Services\Discovery;

use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Services\MoneyPresenter;
use App\Services\TourAvailabilityService;
use App\Services\TourBookingPricingService;
use App\Support\Localization;
use App\Support\ModuleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tour search contract (Phase 13C).
 *
 * Publicly visible tours only (active + approved), Tours module gated.
 * Localized titles reuse the Phase 13A layer where integrated; display
 * money is presentation only (authoritative INR stays on the record).
 * Rating facets use approved reviews only via withAvg/withCount.
 */
final class TourSearchService
{
    /**
     * @return array<int, string>
     */
    public static function allowedSorts(): array
    {
        return config('search.tour_sorts', ['recommended', 'price_asc', 'price_desc', 'duration_asc']);
    }

    public function __construct(
        protected TourAvailabilityService $availability,
        protected ModuleManager $modules,
    ) {}

    public function available(): bool
    {
        return $this->modules->isEnabled(ModuleManager::TOURS);
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,meta:array{current_page:int,per_page:int,total:int,last_page:int},facets:array<string,mixed>,sort:string,seo:array{noindex:bool,canonical:?string}}
     */
    public function search(TourSearchQuery $query, ?string $locale = null): array
    {
        $locale ??= Localization::currentLocale();

        if (! $this->available()) {
            return $this->empty($query);
        }

        $base = $this->baseQuery($query, $locale);

        $facets = $this->facets(clone $base);

        $total = (clone $base)->count();

        $this->applySort($base, $query);

        $perPage = $query->perPage;
        $page = $query->page;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $tours = $base
            ->with(['city:id,name', 'category:id,name,slug'])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->offset(($page - 1) * $perPage)->limit($perPage)
            ->get();

        // Travel-date bookability is evaluated per card (bounded to the
        // page) via the authoritative service — never at SQL scale.
        $data = $tours->map(function (TourPackage $tour) use ($query, $locale): array {
            $bookable = null;

            if ($query->travelDate !== null) {
                try {
                    $bookable = $this->availability->isBookableOn($tour, (string) $query->travelDate);
                } catch (\Throwable) {
                    $bookable = false;
                }
            }

            return $this->card($tour, $locale, $bookable);
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
            'facets' => $facets,
            'sort' => in_array($query->sort, self::allowedSorts(), true) ? $query->sort : 'recommended',
            'seo' => [
                'noindex' => true,
                'canonical' => route('search.tours', [], false),
            ],
        ];
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,meta:array{current_page:int,per_page:int,total:int,last_page:int},facets:array<string,mixed>,sort:string,seo:array{noindex:bool,canonical:?string}}
     */
    private function empty(TourSearchQuery $query): array
    {
        return [
            'data' => [],
            'meta' => ['current_page' => 1, 'per_page' => $query->perPage, 'total' => 0, 'last_page' => 1],
            'facets' => ['categories' => [], 'duration_bands' => [], 'tags' => []],
            'sort' => 'recommended',
            'seo' => ['noindex' => true, 'canonical' => route('search.tours', [], false)],
        ];
    }

    private function baseQuery(TourSearchQuery $query, string $locale): Builder
    {
        $base = TourPackage::query()->from('tour_packages')->publiclyVisible();

        if (is_string($query->q) && $query->q !== '') {
            $like = SearchTerm::like($query->q);
            $base->where(fn ($inner) => $inner->where('tour_packages.title', 'like', $like)
                ->orWhere('tour_packages.overview', 'like', $like));
        }

        $this->applyLocationFilter($base, $query);

        if ($query->categoryId !== null) {
            $base->where('tour_packages.category_id', $query->categoryId);
        }

        if ($query->minDuration !== null) {
            $base->where('tour_packages.duration_days', '>=', $query->minDuration);
        }

        if ($query->maxDuration !== null) {
            $base->where('tour_packages.duration_days', '<=', $query->maxDuration);
        }

        if ($query->minPrice !== null && is_numeric($query->minPrice)) {
            $base->whereRaw('COALESCE(discounted_price, price) >= ?', [number_format((float) $query->minPrice, 2, '.', '')]);
        }

        if ($query->maxPrice !== null && is_numeric($query->maxPrice)) {
            $base->whereRaw('COALESCE(discounted_price, price) <= ?', [number_format((float) $query->maxPrice, 2, '.', '')]);
        }

        foreach ($query->tags as $slug) {
            $base->whereHas('tags', fn ($inner) => $inner->where('tags.is_active', true)->where('tags.slug', $slug));
        }

        if ($query->featuredOnly) {
            $base->where('tour_packages.is_featured', true);
        }

        // Rating filter uses approved reviews only (withAvg subquery).
        if ($query->minRating !== null) {
            $min = max(0, min(5, $query->minRating));
            // Constrain through an exists-average subquery for correctness
            // on SQLite/MySQL (the card aggregates load separately).
            $base->whereRaw(
                '(select avg(rating) from reviews where reviews.package_id = tour_packages.id and reviews.is_approved = 1) >= ?',
                [$min]
            );
        }

        return $base;
    }

    private function applyLocationFilter(Builder $base, TourSearchQuery $query): void
    {
        if ($query->locationType === null || $query->locationId === null) {
            return;
        }

        match ($query->locationType) {
            'city' => $base->where('tour_packages.city_id', $query->locationId),
            'destination' => $base->whereHas(
                'destinations',
                fn ($inner) => $inner->where('destinations.id', $query->locationId)
            ),
            'place' => $base->whereHas(
                'places',
                fn ($inner) => $inner->where('places.id', $query->locationId)
            ),
            default => null,
        };
    }

    private function applySort(Builder $base, TourSearchQuery $query): void
    {
        $sort = in_array($query->sort, self::allowedSorts(), true) ? $query->sort : 'recommended';

        match ($sort) {
            'price_asc' => $base->orderByRaw('COALESCE(discounted_price, price) asc')->orderBy('tour_packages.id'),
            'price_desc' => $base->orderByRaw('COALESCE(discounted_price, price) desc')->orderBy('tour_packages.id'),
            'duration_asc' => $base->orderBy('tour_packages.duration_days')->orderBy('tour_packages.id'),
            default => $base->orderByDesc('tour_packages.is_featured')->orderBy('tour_packages.title')->orderBy('tour_packages.id'),
        };
    }

    /**
     * Compact public-safe tour card. No gallery dump, no vendor data.
     *
     * @return array<string, mixed>
     */
    private function card(TourPackage $tour, string $locale, ?bool $bookable): array
    {
        $effective = (float) $tour->effective_price;
        $display = MoneyPresenter::present($effective, TourBookingPricingService::DEFAULT_CURRENCY);

        // TourPackage carries both a legacy string `category` column and a
        // `category()` relation via category_id. Resolve safely for cards.
        $categoryName = null;
        $related = $tour->relationLoaded('category') ? $tour->getRelation('category') : null;

        if ($related instanceof TourCategory) {
            $categoryName = $related->name;
        } elseif (is_string($tour->getAttribute('category')) && trim($tour->getAttribute('category')) !== '') {
            $categoryName = $tour->getAttribute('category');
        }

        return [
            'id' => (int) $tour->id,
            'slug' => (string) $tour->slug,
            'title' => DiscoveryResult::displayName($tour, 'title', $locale) ?? $tour->title,
            'image' => $tour->cover_image,
            'destination' => $tour->city?->name,
            'category' => $categoryName,
            'duration_days' => (int) $tour->duration_days,
            'duration_nights' => (int) $tour->duration_nights,
            'rating_average' => $tour->approved_reviews_avg_rating !== null
                ? number_format((float) $tour->approved_reviews_avg_rating, 2, '.', '')
                : null,
            'reviews_count' => (int) ($tour->approved_reviews_count ?? 0),
            'starting_price' => [
                'amount' => number_format($effective, 2, '.', ''),
                'currency' => TourBookingPricingService::DEFAULT_CURRENCY,
            ],
            'display_money' => $display,
            'badges' => array_values(array_filter([
                $tour->is_featured ? 'featured' : null,
            ])),
            'bookable_on_date' => $bookable,
            'url' => route('packages.show', $tour, false),
        ];
    }

    /**
     * @return array{categories:array<int,array{id:int,name:string,count:int}>,duration_bands:array<int,array{label:string,min:?int,max:?int,count:int}>,tags:array<int,array{id:int,name:string,count:int}>}
     */
    private function facets(Builder $base): array
    {
        $ids = (clone $base)->pluck('tour_packages.id')->all();

        if ($ids === []) {
            return ['categories' => [], 'duration_bands' => [], 'tags' => []];
        }

        $categories = TourPackage::query()->whereIn('tour_packages.id', $ids)
            ->join('tour_categories', 'tour_categories.id', '=', 'tour_packages.category_id')
            ->selectRaw('tour_categories.id as id, tour_categories.name as name, COUNT(*) as count')
            ->groupBy('tour_categories.id', 'tour_categories.name')
            ->orderBy('tour_categories.name')->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'count' => (int) $row->count])
            ->all();

        $bands = [];
        $definitions = [
            ['label' => '1–3 days', 'min' => 1, 'max' => 3],
            ['label' => '4–7 days', 'min' => 4, 'max' => 7],
            ['label' => '8+ days', 'min' => 8, 'max' => null],
        ];

        foreach ($definitions as $band) {
            $count = TourPackage::query()->whereIn('id', $ids)
                ->when($band['min'] !== null, fn ($inner) => $inner->where('duration_days', '>=', $band['min']))
                ->when($band['max'] !== null, fn ($inner) => $inner->where('duration_days', '<=', $band['max']))
                ->count();

            $bands[] = ['label' => $band['label'], 'min' => $band['min'], 'max' => $band['max'], 'count' => $count];
        }

        $tags = DB::table('tag_tour_package')
            ->join('tags', 'tags.id', '=', 'tag_tour_package.tag_id')
            ->whereIn('tag_tour_package.tour_package_id', $ids)
            ->where('tags.is_active', true)
            ->selectRaw('tags.id as id, tags.name as name, COUNT(DISTINCT tag_tour_package.tour_package_id) as count')
            ->groupBy('tags.id', 'tags.name')
            ->orderBy('tags.name')->limit(20)->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'count' => (int) $row->count])
            ->all();

        return ['categories' => $categories, 'duration_bands' => $bands, 'tags' => $tags];
    }
}
