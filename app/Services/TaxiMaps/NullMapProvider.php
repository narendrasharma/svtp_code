<?php

namespace App\Services\TaxiMaps;

/**
 * Default provider (Phase 12A.6).
 *
 * The product fully works without any map credentials: bookings,
 * pricing, dispatch and coordinate telemetry are unaffected; map UI
 * renders a friendly "not configured" state instead of failing.
 */
class NullMapProvider implements TaxiMapProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function route(float $originLat, float $originLng, float $destLat, float $destLng): TaxiRouteResult
    {
        return TaxiRouteResult::unavailable('none', 'Map provider not configured.');
    }

    public function browserConfig(): array
    {
        return ['provider' => 'none', 'enabled' => false, 'public_key' => null];
    }
}
