<?php

namespace App\Services\TaxiMaps;

/**
 * Mapbox seam (Phase 12A.6).
 *
 * Adapter-ready but NOT implemented in this phase: it satisfies the
 * provider contract and degrades gracefully so selecting Mapbox never
 * breaks Taxi workflows. A future phase implements route() against the
 * Mapbox Directions API plus browserConfig() with the public token —
 * no controller, service or component changes required.
 */
class MapboxMapProvider implements TaxiMapProvider
{
    public function name(): string
    {
        return 'mapbox';
    }

    public function route(float $originLat, float $originLng, float $destLat, float $destLng): TaxiRouteResult
    {
        return TaxiRouteResult::unavailable('mapbox', 'Mapbox routing is not implemented in this phase.');
    }

    public function browserConfig(): array
    {
        return ['provider' => 'mapbox', 'enabled' => false, 'public_key' => null];
    }
}
