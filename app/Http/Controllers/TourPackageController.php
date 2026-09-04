<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\Review;
use App\Models\Tag;
use App\Models\TourCategory;
use App\Models\TourPackage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TourPackageController extends Controller
{
    public function index(Request $request)
    {
        $selectedTags = collect($request->input('tags', []))
            ->filter(fn ($tag): bool => is_string($tag) && $tag !== '')
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $packages = TourPackage::active()
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
                ->whereHas('tourPackages', fn ($query) => $query->active())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon']),
            'places' => Place::query()
                ->whereHas('tourPackages', fn ($query) => $query->active())
                ->with('destination:id,name')
                ->orderBy('name')
                ->get(['id', 'destination_id', 'name', 'slug']),
            'tags' => Tag::query()
                ->where('is_active', true)
                ->whereHas('tourPackages', fn ($query) => $query->active())
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

    public function show(TourPackage $package)
    {
        $package->load(['city', 'category'])
            ->loadCount('approvedReviews')
            ->loadAvg('approvedReviews', 'rating');

        $reviews = $package->approvedReviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(5)
            ->through(fn (Review $review): array => [
                'id' => $review->id,
                'reviewer_name' => $review->reviewer_name ?: $review->user?->name ?: 'Guest',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
            ]);

        return Inertia::render('Packages/Show', compact('package', 'reviews'));
    }
}
