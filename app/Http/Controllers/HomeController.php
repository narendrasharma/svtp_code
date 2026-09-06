<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Destination;
use App\Models\Review;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $featured = TourPackage::active()->featured()->with('city')
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->take(6)->get();

       // $banners = Cache::remember('home.banners', 4, fn () => Banner::active()->get());
        $banners = Cache::remember(
            'home.banners',
            4,
            fn () => Banner::active()
                ->orderBy('sort_order')
                ->get()
        );
        $destinations = Cache::remember(
            'home.destinations.autocomplete',
            3600,
            fn () => Destination::query()
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
        );

        $testimonials = Cache::remember('home.testimonials', 1800, function () {
            return Review::where('is_approved', true)
                ->with('user:id,name', 'package:id,title')
                ->latest()->take(9)->get();
        });

        return Inertia::render('Home', compact('featured', 'banners', 'destinations', 'testimonials'));
    }
}
