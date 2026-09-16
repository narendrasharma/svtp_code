<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePlaceRequest;
use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(Request $request): Response
    {
        // Eager‑load destination **and** its city so the city name is available
        $query = Place::with(['destination.city'])->withCount('tourPackages');

        // -----------------------------------------------------------------
        // Search (name, slug)
        // -----------------------------------------------------------------
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // -----------------------------------------------------------------
        // Destination filter
        // -----------------------------------------------------------------
        if ($destination = $request->query('destination')) {
            $query->where('destination_id', $destination);
        }

        // -----------------------------------------------------------------
        // City filter (via destination -> city)
        // -----------------------------------------------------------------
        if ($city = $request->query('city')) {
            $query->whereHas('destination.city', function ($q) use ($city) {
                $q->where('id', $city);
            });
        }

        // -----------------------------------------------------------------
        // Sorting
        // -----------------------------------------------------------------
        $sortable = ['name', 'created_at'];
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, $sortable)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        // -----------------------------------------------------------------
        // Per‑page selector
        // -----------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 15);
        // Added 15 to the whitelist so the UI option works correctly
        $perPage = in_array($perPage, [10, 15, 25, 50, 100]) ? $perPage : 15;

        $places = $query->paginate($perPage)->appends($request->query());

        return Inertia::render('Admin/Places/Index', [
            'places' => $places,
            'destinations' => Destination::orderBy('name')->get(['id', 'name']),
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search ?? '',
                'destination' => $destination ?? '',
                'city' => $city ?? '',
                'per_page' => $perPage,
                'sort' => $sort ?? '',
                'direction' => $direction ?? '',
            ],
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
