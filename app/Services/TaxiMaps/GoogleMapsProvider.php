<?php

namespace App\Services\TaxiMaps;

use App\Support\TaxiSettings;
use Illuminate\Support\Facades\Http;

/**
 * Google Maps adapter (Phase 12A.6).
 *
 * Server routing uses the Directions API with the SERVER key; browser
 * rendering uses the separate BROWSER key (see browserConfig()). The
 * two credentials serve different trust zones and are configured
 * independently. Every failure degrades to an unavailable result —
 * this adapter never throws and never logs key material.
 */
class GoogleMapsProvider implements TaxiMapProvider
{
    public function name(): string
    {
        return 'google';
    }

    public function route(float $originLat, float $originLng, float $destLat, float $destLng): TaxiRouteResult
    {
        $key = (string) (TaxiSettings::get('taxi.maps.google.server_key') ?? '');

        if ($key === '') {
            return TaxiRouteResult::unavailable('google', 'Google routing key is not configured.');
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => $originLat.','.$originLng,
                'destination' => $destLat.','.$destLng,
                'key' => $key,
            ]);

            return $this->normalize($response->json());
        } catch (\Throwable) {
            return TaxiRouteResult::unavailable('google', 'Route unavailable. Please try again.');
        }
    }

    public function browserConfig(): array
    {
        $publicKey = (string) (TaxiSettings::get('taxi.maps.google.browser_key') ?? '');

        return [
            'provider' => 'google',
            'enabled' => TaxiSettings::enabled('taxi.maps.enabled') && $publicKey !== '',
            'public_key' => $publicKey !== '' ? $publicKey : null,
        ];
    }

    protected function normalize(mixed $payload): TaxiRouteResult
    {
        if (! is_array($payload) || ($payload['status'] ?? null) !== 'OK') {
            return TaxiRouteResult::unavailable('google', 'Route unavailable.');
        }

        $leg = $payload['routes'][0]['legs'][0] ?? null;

        if (! is_array($leg) || ! isset($leg['distance']['value'], $leg['duration']['value'])) {
            return TaxiRouteResult::unavailable('google', 'Route unavailable.');
        }

        $polyline = $payload['routes'][0]['overview_polyline']['points'] ?? null;
        $path = is_string($polyline) ? self::decodePolyline($polyline, 500) : [];

        return new TaxiRouteResult(
            provider: 'google',
            available: true,
            reason: null,
            distanceMeters: (int) $leg['distance']['value'],
            durationSeconds: (int) $leg['duration']['value'],
            polyline: is_string($polyline) ? $polyline : null,
            path: $path,
            bounds: self::boundsFor($path),
            calculatedAt: now(),
        );
    }

    /**
     * Decode a Google encoded polyline, capped to keep payloads small.
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function decodePolyline(string $encoded, int $limit = 500): array
    {
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($encoded);

        while ($index < $length && count($points) < $limit) {
            $shift = 0;
            $result = 0;

            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20 && $index < $length);

            $lat += ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $shift = 0;
            $result = 0;

            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20 && $index < $length);

            $lng += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $points[] = ['lat' => $lat / 1e5, 'lng' => $lng / 1e5];
        }

        return $points;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $path
     * @return array{ne: array{lat: float, lng: float}, sw: array{lat: float, lng: float}}|null
     */
    protected static function boundsFor(array $path): ?array
    {
        if ($path === []) {
            return null;
        }

        $lats = array_column($path, 'lat');
        $lngs = array_column($path, 'lng');

        return [
            'ne' => ['lat' => max($lats), 'lng' => max($lngs)],
            'sw' => ['lat' => min($lats), 'lng' => min($lngs)],
        ];
    }
}
