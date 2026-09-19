<?php

namespace App\Services;

use App\Models\TaxiBooking;
use App\Models\TaxiDriverLocation;
use App\Services\TaxiMaps\GoogleMapsProvider;
use App\Services\TaxiMaps\MapboxMapProvider;
use App\Services\TaxiMaps\NullMapProvider;
use App\Services\TaxiMaps\TaxiMapProvider;
use App\Services\TaxiMaps\TaxiRouteResult;
use App\Support\TaxiSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Provider-neutral routing entry point (Phase 12A.6).
 *
 * Controllers and Vue components use only this service. Provider
 * failures, unknown providers and disabled routing all degrade to an
 * unavailable TaxiRouteResult — routing problems never break Taxi
 * workflows. Responses are cached per rounded coordinate pair; no
 * provider call happens on every render, and completed trips never
 * trigger routing at all.
 */
class TaxiRouteService
{
    public function providerName(): string
    {
        $name = strtolower((string) (TaxiSettings::get('taxi.maps.provider') ?? 'none'));

        return in_array($name, ['none', 'google', 'mapbox'], true) ? $name : 'none';
    }

    public function routingEnabled(): bool
    {
        return TaxiSettings::enabled('taxi.routing.enabled') && $this->providerName() !== 'none';
    }

    public function mapsEnabled(): bool
    {
        return TaxiSettings::enabled('taxi.maps.enabled') && $this->providerName() !== 'none';
    }

    public function cacheMinutes(): int
    {
        return min(120, max(1, (int) (TaxiSettings::get('taxi.routing.cache_minutes') ?? 10)));
    }

    public function refreshSeconds(): int
    {
        return min(300, max(30, (int) (TaxiSettings::get('taxi.routing.refresh_seconds') ?? 60)));
    }

    public function provider(): TaxiMapProvider
    {
        return match ($this->providerName()) {
            'google' => app(GoogleMapsProvider::class),
            'mapbox' => app(MapboxMapProvider::class),
            default => new NullMapProvider,
        };
    }

    /**
     * Browser-safe map configuration for Inertia props. Contains the
     * provider name plus a PUBLIC browser key/token only — server
     * secrets are never included.
     *
     * @return array{provider:string, enabled:bool, public_key:?string, refresh_seconds:int}
     */
    public function browserMapConfig(): array
    {
        $config = $this->provider()->browserConfig();
        $config['refresh_seconds'] = $this->refreshSeconds();

        unset($config['server_key']);

        return $config;
    }

    /**
     * Route driver position (when fresh) or pickup → destination.
     * Returns null when routing is disabled, the trip is not active,
     * or coordinates are missing — never throws.
     */
    public function routeForBooking(TaxiBooking $booking, ?TaxiDriverLocation $driverLocation = null): ?TaxiRouteResult
    {
        if (! $this->routingEnabled()) {
            return null;
        }

        if (! in_array($booking->status, TaxiDriverLocationService::trackedStatuses(), true)) {
            return null;
        }

        $origin = $this->originFor($booking, $driverLocation);

        if ($origin === null || $booking->drop_lat === null || $booking->drop_lng === null) {
            return null;
        }

        return $this->route($origin[0], $origin[1], (float) $booking->drop_lat, (float) $booking->drop_lng);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    protected function originFor(TaxiBooking $booking, ?TaxiDriverLocation $driverLocation): ?array
    {
        if ($driverLocation !== null && $driverLocation->captured_at !== null
            && $driverLocation->captured_at->gte(now()->subSeconds($this->staleSeconds()))) {
            return [(float) $driverLocation->latitude, (float) $driverLocation->longitude];
        }

        if ($booking->pickup_lat !== null && $booking->pickup_lng !== null) {
            return [(float) $booking->pickup_lat, (float) $booking->pickup_lng];
        }

        return null;
    }

    protected function staleSeconds(): int
    {
        return app(TaxiDriverLocationService::class)->staleSeconds();
    }

    /**
     * Full map payload for one trip: browser-safe map config, markers
     * (driver/pickup/drop where coordinates exist) and the cached route.
     * Coordinates already enforced by the caller's scope — this method
     * performs no authorization itself.
     *
     * @return array{map: array{provider:string, enabled:bool, public_key:?string, refresh_seconds:int}, markers: array<int, array{kind:string, lat:float, lng:float, label:string}>, route:?array{provider:string, available:bool, reason:?string, distance_meters:?int, duration_seconds:?int, eta:?string, polyline:?string, path:array<int, array{lat:float, lng:float}>, bounds:?array{ne:array{lat:float, lng:float}, sw:array{lat:float, lng:float}}, calculated_at:string, cached:bool}}
     */
    public function tripMapPayload(TaxiBooking $booking, ?TaxiDriverLocation $driverLocation = null): array
    {
        $markers = [];

        if ($driverLocation !== null) {
            $markers[] = [
                'kind' => 'driver',
                'lat' => (float) $driverLocation->latitude,
                'lng' => (float) $driverLocation->longitude,
                'label' => 'Driver',
            ];
        }

        if ($booking->pickup_lat !== null && $booking->pickup_lng !== null) {
            $markers[] = [
                'kind' => 'pickup',
                'lat' => (float) $booking->pickup_lat,
                'lng' => (float) $booking->pickup_lng,
                'label' => 'Pickup',
            ];
        }

        if ($booking->drop_lat !== null && $booking->drop_lng !== null) {
            $markers[] = [
                'kind' => 'drop',
                'lat' => (float) $booking->drop_lat,
                'lng' => (float) $booking->drop_lng,
                'label' => 'Destination',
            ];
        }

        $route = $this->routeForBooking($booking, $driverLocation);

        return [
            'map' => $this->browserMapConfig(),
            'markers' => $markers,
            'route' => $route?->toArray(),
        ];
    }

    /**
     * Cached, rounded, never-throwing route lookup.
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): TaxiRouteResult
    {
        $provider = $this->providerName();

        if (! $this->routingEnabled()) {
            return TaxiRouteResult::unavailable($provider, 'Routing is disabled.');
        }

        $key = sprintf(
            'taxi:route:%s:%s:%s:%s:%s',
            $provider,
            $this->roundCoord($originLat),
            $this->roundCoord($originLng),
            $this->roundCoord($destLat),
            $this->roundCoord($destLng),
        );

        try {
            $cached = Cache::remember($key, now()->addMinutes($this->cacheMinutes()), fn (): array => $this
                ->provider()
                ->route($originLat, $originLng, $destLat, $destLng)
                ->toArray());

            return $this->resultFromCache($provider, $cached);
        } catch (\Throwable $e) {
            Log::warning('Taxi route lookup failed.', ['provider' => $provider, 'error' => $e->getMessage()]);

            return TaxiRouteResult::unavailable($provider, 'Route unavailable. Please try again.');
        }
    }

    protected function roundCoord(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    protected function resultFromCache(string $provider, array $cached): TaxiRouteResult
    {
        return new TaxiRouteResult(
            provider: $provider,
            available: (bool) ($cached['available'] ?? false),
            reason: $cached['reason'] ?? null,
            distanceMeters: isset($cached['distance_meters']) ? (int) $cached['distance_meters'] : null,
            durationSeconds: isset($cached['duration_seconds']) ? (int) $cached['duration_seconds'] : null,
            polyline: $cached['polyline'] ?? null,
            path: $cached['path'] ?? [],
            bounds: $cached['bounds'] ?? null,
            calculatedAt: isset($cached['calculated_at']) ? Carbon::parse($cached['calculated_at']) : now(),
            cached: true,
        );
    }
}
