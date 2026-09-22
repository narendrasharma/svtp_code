<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCountryRequest;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared geography: Country management (12B.4.1).
 *
 * Platform-level data — deliberately NOT nested under Hotels/Tours/Taxi
 * and NOT module-gated, so every module keeps working even when a
 * single module is disabled. Vendors can only select countries; only
 * staff with locations.countries.* permissions can write.
 */
class CountryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Country::withCount(['states', 'cities'])->latest('id');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('iso2', 'like', "%{$search}%")
                ->orWhere('iso3', 'like', "%{$search}%"));
        }

        if (($status = $request->query('status')) === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        return Inertia::render('Admin/Locations/Countries/Index', [
            'countries' => $query->paginate($perPage)->appends($request->query()),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Locations/Countries/Form');
    }

    public function store(SaveCountryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['iso2'] = strtoupper((string) $data['iso2']);

        if (! empty($data['iso3'])) {
            $data['iso3'] = strtoupper((string) $data['iso3']);
        }

        if (! empty($data['currency_code'])) {
            $data['currency_code'] = strtoupper((string) $data['currency_code']);
        }

        Country::create($data);

        return redirect()->route('admin.countries.index')->with('flash', 'Country created.');
    }

    public function edit(Country $country): Response
    {
        return Inertia::render('Admin/Locations/Countries/Form', [
            'country' => $country,
        ]);
    }

    public function update(SaveCountryRequest $request, Country $country): RedirectResponse
    {
        $data = $request->validated();
        $data['iso2'] = strtoupper((string) $data['iso2']);

        if (! empty($data['iso3'])) {
            $data['iso3'] = strtoupper((string) $data['iso3']);
        }

        if (! empty($data['currency_code'])) {
            $data['currency_code'] = strtoupper((string) $data['currency_code']);
        }

        $country->update($data);

        return redirect()->route('admin.countries.index')->with('flash', 'Country updated.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        // Soft-block while referenced: state/city/destination/property
        // links null out via FK, but an explicit guard keeps curated
        // geography from disappearing under live listings by accident.
        abort_if(
            $country->states()->exists() || $country->cities()->exists()
            || $country->destinations()->exists() || $country->properties()->exists(),
            422,
            'This country is still referenced by geography records.'
        );

        $country->delete();

        return back()->with('flash', 'Country removed.');
    }
}
