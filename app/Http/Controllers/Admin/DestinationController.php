<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDestinationRequest;
use App\Models\City;
use App\Models\Destination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Destinations/Index', [
            'destinations' => Destination::with('city')->withCount(['places', 'tourPackages'])->latest()->paginate(10),
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
        $data = $request->safe()->except(['image_upload', 'remove_image']);

        if ($request->hasFile('image_upload')) {
            $data['image'] = Storage::disk('public')->url($request->file('image_upload')->store('destinations', 'public'));
        }

        Destination::create($data);
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
        $data = $request->safe()->except(['image_upload', 'remove_image']);
        $oldImage = $destination->image;

        if ($request->hasFile('image_upload')) {
            $data['image'] = Storage::disk('public')->url($request->file('image_upload')->store('destinations', 'public'));
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        }

        $destination->update($data);

        if (array_key_exists('image', $data) && $oldImage !== $data['image']) {
            $this->deleteManagedImage($oldImage);
        }
        Cache::forget('home.destinations.autocomplete');

        return redirect()->route('admin.destinations.index')->with('flash', 'Destination updated.');
    }

    public function destroy(Destination $destination): RedirectResponse
    {
        $destination->delete();
        Cache::forget('home.destinations.autocomplete');

        return back()->with('flash', 'Destination removed.');
    }

    private function deleteManagedImage(?string $image): void
    {
        $path = $image ? parse_url($image, PHP_URL_PATH) : null;

        if (is_string($path) && str_starts_with($path, '/storage/destinations/')) {
            Storage::disk('public')->delete(substr($path, strlen('/storage/')));
        }
    }
}
