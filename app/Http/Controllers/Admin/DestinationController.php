<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDestinationRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Destination;
use App\Models\State;
use App\Services\LocationHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Destination::with('city')->withCount(['places', 'tourPackages']);

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
        // Status filter (active / inactive)
        // -----------------------------------------------------------------
        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // -----------------------------------------------------------------
        // Sorting
        // -----------------------------------------------------------------
        $sortable = ['name', 'created_at', 'is_active'];
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
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $destinations = $query->paginate($perPage)->appends($request->query());

        return Inertia::render('Admin/Destinations/Index', [
            'destinations' => $destinations,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'per_page' => $perPage,
                'sort' => $sort ?? '',
                'direction' => $direction ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Destinations/Form', $this->formData());
    }

    public function store(SaveDestinationRequest $request): RedirectResponse
    {
        $data = LocationHierarchy::inheritCountry($request->safe()->except(['image_upload', 'remove_image']));

        if ($request->hasFile('image_upload')) {
            $data['image'] = Storage::disk('public')->url($request->file('image_upload')->store('destinations', 'public'));
        }

        Destination::create($data);
        Cache::forget('home.destinations.autocomplete');

        return redirect()->route('admin.destinations.index')->with('flash', 'Destination created.');
    }

    public function edit(Destination $destination): Response
    {
        return Inertia::render('Admin/Destinations/Form', $this->formData($destination));
    }

    public function update(SaveDestinationRequest $request, Destination $destination): RedirectResponse
    {
        $data = LocationHierarchy::inheritCountry($request->safe()->except(['image_upload', 'remove_image']));
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

    /**
     * Shared geography form data (12B.4.1): cities carry their country/
     * state context so the form can scope dependent selects; parents
     * exclude the edited record to prevent self-parenting client-side
     * (server still enforces hierarchy + cycle rules).
     *
     * @return array<string, mixed>
     */
    protected function formData(?Destination $destination = null): array
    {
        return [
            'destination' => $destination,
            'countries' => Country::ordered()->get(['id', 'name']),
            'states' => State::ordered()->get(['id', 'country_id', 'name']),
            'cities' => City::ordered()->get(['id', 'country_id', 'state_id', 'name']),
            'parents' => Destination::ordered()
                ->when($destination, fn ($query) => $query->whereKeyNot($destination->id))
                ->limit(500)
                ->get(['id', 'name']),
            'destinationTypes' => Destination::types(),
        ];
    }
}
