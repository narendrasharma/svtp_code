<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePlaceRequest;
use App\Models\Destination;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Places/Index', [
            'places' => Place::with('destination')->withCount('tourPackages')->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Places/Form', [
            'destinations' => Destination::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SavePlaceRequest $request): RedirectResponse
    {
        Place::create($request->validated());

        return redirect()->route('admin.places.index')->with('flash', 'Place created.');
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('Admin/Places/Form', [
            'place' => $place,
            'destinations' => Destination::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(SavePlaceRequest $request, Place $place): RedirectResponse
    {
        $place->update($request->validated());

        return redirect()->route('admin.places.index')->with('flash', 'Place updated.');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $place->delete();

        return back()->with('flash', 'Place removed.');
    }
}
