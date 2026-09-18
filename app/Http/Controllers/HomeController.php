<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Destination;
use App\Models\Review;
use App\Models\TourPackage;
use App\Services\HomepageSectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $composer = app(HomepageSectionService::class);
        $homepageSections = $composer->forHomepage();
        $keys = $homepageSections->pluck('key');
        $settingsFor = fn (string $key): array => $homepageSections->firstWhere('key', $key)['settings'] ?? [];

        $featured = $this->featuredPackages($keys, $settingsFor('featured_packages'));
        [$banners, $destinations] = $this->heroData($keys, $settingsFor('hero'));
        $testimonials = $this->testimonials($keys, $settingsFor('testimonials'));

        return Inertia::render('Home', [
            'homepageSections' => $homepageSections->values()->all(),
            'featured' => $featured,
            'banners' => $banners,
            'destinations' => $destinations,
            'testimonials' => $testimonials,
        ]);
    }

    /**
     * Read a plain-array payload from cache, rebuilding it when the entry is
     * missing or holds anything else.
     *
     * Eloquent models must never be cached directly: this application ships
     * with `cache.serializable_classes => false`, so unserializing model
     * instances yields __PHP_Incomplete_Class (a 500 on the next deploy that
     * reads a stale entry). Plain arrays survive safely across deploys.
     *
     * @return array<int|string, mixed>
     */
    protected function rememberPlainArray(string $key, int $ttl, callable $rebuild): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($key);
        }

        $fresh = $rebuild();

        $data = $fresh instanceof Collection ? $fresh->all() : (array) $fresh;
        Cache::put($key, $data, $ttl);

        return $data;
    }

    /**
     * @param  Collection<int, string>  $keys
     * @param  array<string, mixed>  $config
     */
    protected function featuredPackages(Collection $keys, array $config): Collection
    {
        if (! $keys->contains('featured_packages')) {
            return collect();
        }

        $take = max(1, min(12, (int) ($config['max_items'] ?? 6)));
        $query = TourPackage::publiclyVisible()->with('city')
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating');

        if (($config['source'] ?? 'featured') === 'latest') {
            $query->latest();
        } else {
            $query->featured();
        }

        return $query->take($take)->get();
    }

    /**
     * Hero banners plus the destination autocomplete backing the hero search.
     * Neither is loaded when the hero (or its search) is disabled.
     *
     * @param  Collection<int, string>  $keys
     * @param  array<string, mixed>  $config
     * @return array{0: Collection, 1: Collection}
     */
    protected function heroData(Collection $keys, array $config): array
    {
        if (! $keys->contains('hero')) {
            return [collect(), collect()];
        }

        // Banners are cached as plain arrays (see rememberPlainArray).
        $banners = collect($this->rememberPlainArray(
            'home.banners',
            4,
            fn (): array => Banner::active()
                ->orderBy('sort_order')
                ->get()
                ->toArray()
        ));

        if (! ($config['show_search'] ?? true)) {
            return [$banners, collect()];
        }

        $destinations = collect($this->rememberPlainArray(
            'home.destinations.autocomplete',
            3600,
            fn (): array => Destination::query()
                ->with('city:id,state_id,name,is_spiritual_hub', 'city.state:id,name')
                ->orderBy('name')
                ->get(['id', 'city_id', 'name', 'slug'])
                ->map(fn (Destination $destination): array => [
                    'id' => $destination->id,
                    'name' => $destination->name,
                    'slug' => $destination->slug,
                    'city' => $destination->city ? [
                        'name' => $destination->city->name,
                        'is_spiritual_hub' => $destination->city->is_spiritual_hub,
                        'state' => $destination->city->state ? [
                            'name' => $destination->city->state->name,
                        ] : null,
                    ] : null,
                ])->values()->all()
        ));

        return [$banners, $destinations];
    }

    /**
     * @param  Collection<int, string>  $keys
     * @param  array<string, mixed>  $config
     */
    protected function testimonials(Collection $keys, array $config): Collection
    {
        if (! $keys->contains('testimonials')) {
            return collect();
        }

        $take = max(1, min(12, (int) ($config['max_items'] ?? 9)));

        return collect($this->rememberPlainArray(
            "home.testimonials.{$take}",
            1800,
            fn (): array => Review::where('is_approved', true)
                ->with('user:id,name', 'package:id,title')
                ->latest()->take($take)->get()
                ->toArray()
        ));
    }
}
