<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\City;
use App\Models\Review;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Cache::remember('home.featured_packages', 3600, function () {
            return TourPackage::active()->featured()->with('city')
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->take(6)->get();
        });

        $banners = Cache::remember('home.banners', 3600, fn () => Banner::active()->get());

        $cities = Cache::remember('home.cities', 3600, fn () => City::withCount([
            'packages' => fn ($query) => $query->active(),
        ])->orderByDesc('is_spiritual_hub')->orderByDesc('packages_count')->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_spiritual_hub']));

        $testimonials = Cache::remember('home.testimonials', 1800, function () {
            return Review::where('is_approved', true)
                ->with('user:id,name', 'package:id,title')
                ->latest()->take(9)->get();
        });

        return Inertia::render('Home', compact('featured', 'banners', 'cities', 'testimonials'));
    }
}
