<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Destination;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared dependent geography lookups (12B.4.1).
 *
 * Bounded (never dumps whole tables) and strictly scoped: states by
 * country, cities by country/state, destinations by country/state/city.
 * Used by Property/Tour admin + vendor forms. Malformed ids are cast to
 * int and safely return empty sets.
 */
class LocationLookupController extends Controller
{
    public function states(Request $request): JsonResponse
    {
        $query = State::active()->ordered()->limit(200);

        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.mb_substr(trim((string) $request->input('search')), 0, 60).'%');
        }

        return response()->json($query->get(['id', 'country_id', 'name'])->map(fn (State $state): array => [
            'id' => $state->id,
            'name' => $state->name,
            'country' => $state->country?->name,
        ]));
    }

    public function cities(Request $request): JsonResponse
    {
        $query = City::active()->with('state:id,name')->ordered()->limit(50);

        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }

        if ($request->filled('state_id')) {
            $query->where('state_id', (int) $request->input('state_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.mb_substr(trim((string) $request->input('search')), 0, 60).'%');
        }

        return response()->json($query->get(['id', 'country_id', 'state_id', 'name'])->map(fn (City $city): array => [
            'id' => $city->id,
            'name' => $city->name,
            'state' => $city->state?->name,
        ]));
    }

    public function destinations(Request $request): JsonResponse
    {
        $query = Destination::active()->with(['city:id,name'])->ordered()->limit(50);

        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }

        if ($request->filled('state_id')) {
            $query->where('state_id', (int) $request->input('state_id'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', (int) $request->input('city_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.mb_substr(trim((string) $request->input('search')), 0, 60).'%');
        }

        return response()->json($query->get(['id', 'country_id', 'state_id', 'city_id', 'name', 'destination_type'])->map(fn (Destination $destination): array => [
            'id' => $destination->id,
            'name' => $destination->name,
            'type' => $destination->destination_type,
            'city' => $destination->city?->name,
        ]));
    }
}
