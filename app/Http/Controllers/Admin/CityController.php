<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCityRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Services\LocationHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared geography: City management (12B.4.1).
 *
 * Platform-level data, not module-gated. City identity stays reusable
 * across Hotels/Tours/discovery; merchandising (featured/sort/image)
 * powers the future Popular Cities sections without extra tables.
 */
class CityController extends Controller
{
    public function index(Request $request): Response
    {
        $query = City::with(['country:id,name', 'state:id,name'])
            ->withCount(['destinations', 'properties'])
            ->latest('id');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"));
        }

        if ($country = $request->query('country')) {
            $query->where('country_id', (int) $country);
        }

        if ($state = $request->query('state')) {
            $query->where('state_id', (int) $state);
        }

        if (($status = $request->query('status')) === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if (($featured = $request->query('featured')) === 'yes') {
            $query->where('is_featured', true);
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        return Inertia::render('Admin/Locations/Cities/Index', [
            'cities' => $query->paginate($perPage)->appends($request->query()),
            'countries' => Country::ordered()->get(['id', 'name']),
            'states' => State::ordered()->get(['id', 'country_id', 'name']),
            'filters' => [
                'search' => $search ?? '',
                'country' => $country ?? '',
                'state' => $state ?? '',
                'status' => $status ?? 'all',
                'featured' => $featured ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Locations/Cities/Form', [
            'countries' => Country::ordered()->get(['id', 'name']),
            'states' => State::ordered()->get(['id', 'country_id', 'name']),
        ]);
    }

    public function store(SaveCityRequest $request): RedirectResponse
    {
        City::create(LocationHierarchy::inheritCountry($request->validated()));

        return redirect()->route('admin.cities.index')->with('flash', 'City created.');
    }

    public function edit(City $city): Response
    {
        return Inertia::render('Admin/Locations/Cities/Form', [
            'city' => $city,
            'countries' => Country::ordered()->get(['id', 'name']),
            'states' => State::ordered()->get(['id', 'country_id', 'name']),
        ]);
    }

    public function update(SaveCityRequest $request, City $city): RedirectResponse
    {
        $city->update(LocationHierarchy::inheritCountry($request->validated()));

        return redirect()->route('admin.cities.index')->with('flash', 'City updated.');
    }

    public function destroy(City $city): RedirectResponse
    {
        abort_if(
            $city->destinations()->exists() || $city->properties()->exists()
            || $city->packages()->exists(),
            422,
            'This city is still referenced by destinations, properties or tours.'
        );

        $city->delete();

        return back()->with('flash', 'City removed.');
    }
}
