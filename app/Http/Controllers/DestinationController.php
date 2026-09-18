<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    public function index(): Response
    {
        // Only show active destinations to the public
        $destinations = Destination::with('city:id,name')
            ->withCount(['places', 'tourPackages' => fn ($query) => $query->publiclyVisible()])
            ->where('is_active', true) // <-- hide inactive destinations
            ->orderBy('name')
            ->get();

        return Inertia::render('Static/Destinations', [
            'destinations' => $destinations,
        ]);
    }

    public function show(Destination $destination): Response
    {
        // If the destination is inactive, treat it as not found
        if (! $destination->is_active) {
            abort(404);
        }

        $destination->load([
            'city:id,name',
            'places' => fn ($query) => $query->orderBy('name'),
            'tourPackages' => fn ($query) => $query->publiclyVisible()
                ->with('city:id,name')
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->orderByDesc('is_featured')
                ->orderBy('title'),
        ]);

        return Inertia::render('Static/Destination', ['destination' => $destination]);
    }
}
