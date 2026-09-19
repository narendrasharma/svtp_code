<?php

namespace App\Http\Controllers\Driver\Taxi;

use App\Services\TaxiDriverLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 12A.5 driver telemetry endpoints (JSON only).
 *
 * Identity comes from the linked Driver record; trip association is
 * derived server-side from the current open assignment. The request
 * carries coordinates and device readings only — driver_id,
 * vendor_profile_id and booking ids are never accepted.
 */
class DriverLocationController extends DriverPortalController
{
    public function __construct(protected TaxiDriverLocationService $tracking) {}

    public function store(Request $request): JsonResponse
    {
        $driver = $this->driver($request);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'captured_at' => ['nullable', 'date'],
        ]);

        $ping = $this->tracking->record($driver, [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'accuracy_meters' => $validated['accuracy_meters'] ?? null,
            'heading' => isset($validated['heading']) ? (float) $validated['heading'] : null,
            'speed_kmh' => isset($validated['speed_kmh']) ? (float) $validated['speed_kmh'] : null,
            'captured_at' => isset($validated['captured_at']) ? Carbon::parse($validated['captured_at']) : now(),
        ]);

        return response()->json([
            'ok' => true,
            'captured_at' => $ping->captured_at->toISOString(),
            'trip_linked' => $ping->taxi_booking_id !== null,
            'freshness' => $this->tracking->freshness($ping),
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $driver = $this->driver($request);

        $latest = $this->tracking->latestFor($driver);

        return response()->json([
            'sharing' => $latest !== null,
            'freshness' => $this->tracking->freshness($latest),
            'last_captured_at' => $latest?->captured_at?->toISOString(),
            'stale_seconds' => $this->tracking->staleSeconds(),
            'update_interval_seconds' => 30,
        ]);
    }
}
