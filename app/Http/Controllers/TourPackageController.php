<?php

namespace App\Http\Controllers;

use App\Models\TourPackage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TourPackageController extends Controller
{
    public function index(Request $request)
    {
        $packages = TourPackage::active()
            ->with('city')
            ->when($request->city, fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $request->city)))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->min_price, fn ($q) => $q->where('price', '>=', $request->min_price))
            ->when($request->max_price, fn ($q) => $q->where('price', '<=', $request->max_price))
            ->paginate(9)->withQueryString();

        return Inertia::render('Packages/Index', [
            'packages' => $packages,
            'filters' => $request->only('city', 'category', 'min_price', 'max_price', 'date'),
        ]);
    }

    public function show(TourPackage $package)
    {
        $package->load('city')
            ->loadCount('approvedReviews')
            ->loadAvg('approvedReviews', 'rating');

        $reviews = $package->approvedReviews()->with('user:id,name')->latest()->paginate(5);

        return Inertia::render('Packages/Show', compact('package', 'reviews'));
    }
}
