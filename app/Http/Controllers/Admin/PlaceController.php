<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePlaceRequest;
use App\Models\Destination;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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
        $data = $request->safe()->except(['image_upload', 'remove_image']);

        if ($request->hasFile('image_upload')) {
            $data['image'] = Storage::disk('public')->url($request->file('image_upload')->store('places', 'public'));
        }

        Place::create($data);

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
        $data = $request->safe()->except(['image_upload', 'remove_image']);
        $oldImage = $place->image;

        if ($request->hasFile('image_upload')) {
            $data['image'] = Storage::disk('public')->url($request->file('image_upload')->store('places', 'public'));
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        }

        $place->update($data);

        if (array_key_exists('image', $data) && $oldImage !== $data['image']) {
            $this->deleteManagedImage($oldImage);
        }

        return redirect()->route('admin.places.index')->with('flash', 'Place updated.');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $place->delete();

        return back()->with('flash', 'Place removed.');
    }

    private function deleteManagedImage(?string $image): void
    {
        $path = $image ? parse_url($image, PHP_URL_PATH) : null;

        if (is_string($path) && str_starts_with($path, '/storage/places/')) {
            Storage::disk('public')->delete(substr($path, strlen('/storage/')));
        }
    }
}
