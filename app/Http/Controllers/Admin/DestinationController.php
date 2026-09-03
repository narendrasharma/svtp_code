<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDestinationRequest;
use App\Models\City;
use App\Models\Destination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Destinations/Index', [
            'destinations' => Destination::with('city')->withCount(['places', 'tourPackages'])->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Destinations/Form', [
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveDestinationRequest $request): RedirectResponse
    {
        Destination::create($request->validated());
        Cache::forget('home.destinations.autocomplete');

        return redirect()->route('admin.destinations.index')->with('flash', 'Destination created.');
    }

    public function edit(Destination $destination): Response
    {
        return Inertia::render('Admin/Destinations/Form', [
            'destination' => $destination,
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(SaveDestinationRequest $request, Destination $destination): RedirectResponse
    {
        $destination->update($request->validated());
        Cache::forget('home.destinations.autocomplete');

        return redirect()->route('admin.destinations.index')->with('flash', 'Destination updated.');
    }

    public function destroy(Destination $destination): RedirectResponse
    {
        $destination->delete();
        Cache::forget('home.destinations.autocomplete');

        return back()->with('flash', 'Destination removed.');
    }
}
