<?php

namespace App\Http\Controllers;

use App\Models\Place;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function show(Place $place): Response
    {
        $place->load([
            'destination.city:id,name',
            'tourPackages' => fn ($query) => $query->publiclyVisible()
                ->with('city:id,name')
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->orderByDesc('is_featured')
                ->orderBy('title'),
        ]);

        return Inertia::render('Static/Place', ['place' => $place]);
    }
}
