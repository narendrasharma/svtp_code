<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Static/Destinations', [
            'destinations' => Destination::with('city:id,name')
                ->withCount(['places', 'tourPackages' => fn ($query) => $query->active()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Destination $destination): Response
    {
        $destination->load([
            'city:id,name',
            'places' => fn ($query) => $query->orderBy('name'),
            'tourPackages' => fn ($query) => $query->active()
                ->with('city:id,name')
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->orderByDesc('is_featured')
                ->orderBy('title'),
        ]);

        return Inertia::render('Static/Destination', ['destination' => $destination]);
    }
}
