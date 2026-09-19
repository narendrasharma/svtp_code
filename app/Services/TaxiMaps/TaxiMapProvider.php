<?php

namespace App\Services\TaxiMaps;

/**
 * Map/routing provider contract (Phase 12A.6).
 *
 * Business code (bookings, dispatch, portal) depends only on this
 * contract plus TaxiRouteService — never on Google/Mapbox classes.
 * A future provider (e.g. Mapbox) is added by implementing this
 * interface and registering it in TaxiRouteService; no controller,
 * service or component changes are needed.
 */
interface TaxiMapProvider
{
    /**
     * Machine name: none|google|mapbox.
     */
    public function name(): string;

    /**
     * Route origin → destination. Must never throw: every failure mode
     * (timeout, quota, invalid key, no route, malformed response) is
     * reported as an unavailable TaxiRouteResult.
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): TaxiRouteResult;

    /**
     * Browser-safe map configuration. MUST contain only the provider
     * name plus public browser keys/tokens — server secrets are never
     * exposed here.
     *
     * @return array{provider:string, enabled:bool, public_key:?string}
     */
    public function browserConfig(): array;
}
