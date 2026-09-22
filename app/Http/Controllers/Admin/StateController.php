<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStateRequest;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared geography: State/Region management (12B.4.1).
 *
 * Platform-level data, not module-gated. States belong to an optional
 * country (legacy rows without a verified country keep working).
 */
class StateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = State::with('country:id,name')->withCount('cities')->latest('id');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        if ($country = $request->query('country')) {
            $query->where('country_id', (int) $country);
        }

        if (($status = $request->query('status')) === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        return Inertia::render('Admin/Locations/States/Index', [
            'states' => $query->paginate($perPage)->appends($request->query()),
            'countries' => Country::ordered()->get(['id', 'name']),
            'filters' => [
                'search' => $search ?? '',
                'country' => $country ?? '',
                'status' => $status ?? 'all',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Locations/States/Form', [
            'countries' => Country::ordered()->get(['id', 'name']),
        ]);
    }

    public function store(SaveStateRequest $request): RedirectResponse
    {
        State::create($request->validated());

        return redirect()->route('admin.states.index')->with('flash', 'State created.');
    }

    public function edit(State $state): Response
    {
        return Inertia::render('Admin/Locations/States/Form', [
            'state' => $state,
            'countries' => Country::ordered()->get(['id', 'name']),
        ]);
    }

    public function update(SaveStateRequest $request, State $state): RedirectResponse
    {
        $state->update($request->validated());

        return redirect()->route('admin.states.index')->with('flash', 'State updated.');
    }

    public function destroy(State $state): RedirectResponse
    {
        abort_if(
            $state->cities()->exists() || $state->destinations()->exists(),
            422,
            'This state is still referenced by cities or destinations.'
        );

        $state->delete();

        return back()->with('flash', 'State removed.');
    }
}
