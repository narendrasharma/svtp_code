<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverLocation;
use App\Models\Vehicle;
use App\Support\TaxiSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Smart dispatch recommendations (Phase 12A.7) — decision support only.
 *
 * Pipeline: hard eligibility (reused availability rules) → cheap
 * Haversine shortlist → routing ETA for the top N only → deterministic
 * ranking with human-readable reasons. Never assigns anything; the
 * operator confirms via the existing assignment endpoints, which
 * revalidate from scratch. Nothing here is trusted as authorization.
 */
class TaxiDispatchRecommendationService
{
    public function __construct(
        protected TaxiAvailabilityService $availability,
        protected TaxiDriverLocationService $tracking,
        protected TaxiRouteService $routes,
    ) {}

    public function smartEnabled(): bool
    {
        return TaxiSettings::enabled('taxi.dispatch.smart_enabled');
    }

    public function maxPickupRadiusKm(): float
    {
        return max(0, (float) (TaxiSettings::get('taxi.dispatch.max_pickup_radius_km') ?? 0));
    }

    public function routingCandidateLimit(): int
    {
        return min(20, max(1, (int) (TaxiSettings::get('taxi.dispatch.routing_candidate_limit') ?? 5)));
    }

    public function useRoutingEta(): bool
    {
        return TaxiSettings::enabled('taxi.dispatch.use_routing_eta');
    }

    /**
     * Ranked driver+vehicle recommendations for a booking. Briefly cached
     * (status-keyed, 60s); assignment-time revalidation makes short TTL
     * staleness harmless.
     *
     * @return array{recommendations: array<int, array{rank:int, driver_id:int, driver_name:string, vehicle_id:int, vehicle_name:string, pickup_distance_km:?float, pickup_eta_minutes:?int, location_state:string, recommendation_reason:string}>, excluded_counts: array<string, int>}
     */
    public function recommend(TaxiBooking $booking): array
    {
        if (! $this->smartEnabled()) {
            return ['recommendations' => [], 'excluded_counts' => ['smart_disabled' => 1]];
        }

        if ($booking->vendor_profile_id === null) {
            return ['recommendations' => [], 'excluded_counts' => ['no_vendor' => 1]];
        }

        return Cache::remember(
            'taxi:reco:'.$booking->id.':'.$booking->status,
            60,
            fn (): array => $this->build($booking->fresh() ?? $booking),
        );
    }

    /**
     * @return array{recommendations: array<int, array{rank:int, driver_id:int, driver_name:string, vehicle_id:int, vehicle_name:string, pickup_distance_km:?float, pickup_eta_minutes:?int, location_state:string, recommendation_reason:string}>, excluded_counts: array<string, int>}
     */
    protected function build(TaxiBooking $booking): array
    {
        $excluded = [];
        $pairs = [];

        $drivers = $this->availability->eligibleDrivers($booking, 200)
            ->filter(fn (array $row): bool => $this->countExcluded($row, $excluded, 'driver'));

        $vehicles = $this->availability->eligibleVehicles($booking, 200)
            ->filter(fn (array $row): bool => $this->countExcluded($row, $excluded, 'vehicle'))
            ->values();

        if ($drivers->isEmpty() || $vehicles->isEmpty()) {
            return ['recommendations' => [], 'excluded_counts' => $excluded];
        }

        foreach ($drivers as $driverRow) {
            /** @var Driver $driver */
            $driver = $driverRow['driver'];
            $vehicleRow = $this->bestVehicle($vehicles);

            if ($vehicleRow === null) {
                continue;
            }

            /** @var Vehicle $vehicle */
            $vehicle = $vehicleRow['vehicle'];
            $pair = $this->availability->checkPair($driver, $vehicle, $booking);

            if (! $pair['eligible']) {
                $excluded['pair_invalid'] = ($excluded['pair_invalid'] ?? 0) + 1;

                continue;
            }

            $pairs[] = $this->score($driver, $vehicle, $booking, $excluded);
        }

        $pairs = array_values(array_filter($pairs));

        $this->applyRoutingEta($booking, $pairs);

        usort($pairs, fn (array $a, array $b): int => [$a['sort_tier'], $a['sort_value'], $a['driver_id']]
            <=> [$b['sort_tier'], $b['sort_value'], $b['driver_id']]);

        $recommendations = [];

        foreach (array_values($pairs) as $index => $pair) {
            $recommendations[] = [
                'rank' => $index + 1,
                'driver_id' => $pair['driver_id'],
                'driver_name' => $pair['driver_name'],
                'vehicle_id' => $pair['vehicle_id'],
                'vehicle_name' => $pair['vehicle_name'],
                'pickup_distance_km' => $pair['pickup_distance_km'],
                'pickup_eta_minutes' => $pair['pickup_eta_minutes'],
                'location_state' => $pair['location_state'],
                'recommendation_reason' => $pair['recommendation_reason'],
            ];
        }

        return ['recommendations' => $recommendations, 'excluded_counts' => $excluded];
    }

    /**
     * @param  array{eligible: bool, reason: ?string, driver?: Driver, vehicle?: Vehicle}  $row
     */
    protected function countExcluded(array $row, array &$excluded, string $kind): bool
    {
        if ($row['eligible']) {
            return true;
        }

        $key = $kind.'_'.preg_replace('/[^a-z_]/', '', strtolower((string) ($row['reason'] ?? 'ineligible')));
        $excluded[$key ?: $kind.'_ineligible'] = ($excluded[$key ?? $kind.'_ineligible'] ?? 0) + 1;

        return false;
    }

    /**
     * Smallest sufficient vehicle keeps fleets efficient; deterministic
     * tie-break on id.
     *
     * @param  Collection<int, array{vehicle: Vehicle, eligible: bool, reason: ?string}>  $vehicles
     * @return array{vehicle: Vehicle, eligible: bool, reason: ?string}|null
     */
    protected function bestVehicle(Collection $vehicles): ?array
    {
        $best = null;

        foreach ($vehicles as $row) {
            if ($best === null
                || $row['vehicle']->passenger_capacity < $best['vehicle']->passenger_capacity
                || ($row['vehicle']->passenger_capacity === $best['vehicle']->passenger_capacity
                    && $row['vehicle']->id < $best['vehicle']->id)) {
                $best = $row;
            }
        }

        return $best;
    }

    /**
     * @return array{driver_id:int, driver_name:string, vehicle_id:int, vehicle_name:string, pickup_distance_km:?float, pickup_eta_minutes:?int, location_state:string, recommendation_reason:string, sort_tier:int, sort_value:float}|null
     */
    protected function score(Driver $driver, Vehicle $vehicle, TaxiBooking $booking, array &$excluded): ?array
    {
        $location = $this->tracking->latestFor($driver);
        $state = $this->tracking->freshness($location);
        $distance = $this->haversineToPickup($location, $booking);

        $radius = $this->maxPickupRadiusKm();

        if ($radius > 0 && $distance !== null && $distance > $radius) {
            $excluded['outside_radius'] = ($excluded['outside_radius'] ?? 0) + 1;

            return null;
        }

        if ($state === 'offline' || $distance === null) {
            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->fullName(),
                'vehicle_id' => $vehicle->id,
                'vehicle_name' => $vehicle->name.' ('.$vehicle->registration_number.')',
                'pickup_distance_km' => null,
                'pickup_eta_minutes' => null,
                'location_state' => $state,
                'recommendation_reason' => 'No live location — availability only',
                'sort_tier' => 2,
                'sort_value' => 0,
                'location' => null,
            ];
        }

        $reason = $state === 'live' ? 'Fresh location' : 'Stale location — ranked lower';

        return [
            'driver_id' => $driver->id,
            'driver_name' => $driver->fullName(),
            'vehicle_id' => $vehicle->id,
            'vehicle_name' => $vehicle->name.' ('.$vehicle->registration_number.')',
            'pickup_distance_km' => round($distance, 1),
            'pickup_eta_minutes' => null,
            'location_state' => $state,
            'recommendation_reason' => $reason.' · ≈'.round($distance, 1).' km straight-line',
            'sort_tier' => $state === 'live' ? 0 : 1,
            'sort_value' => $distance,
            'location' => $location,
        ];
    }

    protected function haversineToPickup(?TaxiDriverLocation $location, TaxiBooking $booking): ?float
    {
        if ($location === null || $booking->pickup_lat === null || $booking->pickup_lng === null) {
            return null;
        }

        return self::haversine(
            (float) $location->latitude,
            (float) $location->longitude,
            (float) $booking->pickup_lat,
            (float) $booking->pickup_lng,
        );
    }

    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthKm * asin(min(1, sqrt($a)));
    }

    /**
     * Paid routing ETA for the shortlist only: nearest located candidates
     * up to the configured limit. Failures keep the Haversine fallback.
     *
     * @param  array<int, array{driver_id:int, driver_name:string, vehicle_id:int, vehicle_name:string, pickup_distance_km:?float, pickup_eta_minutes:?int, location_state:string, recommendation_reason:string, sort_tier:int, sort_value:float}>  $pairs
     */
    protected function applyRoutingEta(TaxiBooking $booking, array &$pairs): void
    {
        if (! $this->useRoutingEta() || $booking->pickup_lat === null || $booking->pickup_lng === null) {
            return;
        }

        $located = array_filter($pairs, fn (array $p): bool => $p['pickup_distance_km'] !== null);
        usort($located, fn (array $a, array $b): int => $a['pickup_distance_km'] <=> $b['pickup_distance_km']);
        $shortlist = array_slice(array_column($located, 'driver_id'), 0, $this->routingCandidateLimit());

        if ($shortlist === []) {
            return;
        }

        $byDriver = [];

        foreach ($pairs as $index => $pair) {
            $byDriver[$pair['driver_id']] = $index;
        }

        foreach ($shortlist as $driverId) {
            $index = $byDriver[$driverId];
            $location = $pairs[$index]['location'] ?? null;

            if ($location === null) {
                continue;
            }

            $route = $this->routes->route(
                (float) $location->latitude,
                (float) $location->longitude,
                (float) $booking->pickup_lat,
                (float) $booking->pickup_lng,
            );

            if (! $route->available || $route->durationSeconds === null) {
                $pairs[$index]['recommendation_reason'] .= ' · routing unavailable, straight-line estimate';

                continue;
            }

            $minutes = (int) round($route->durationSeconds / 60);
            $pairs[$index]['pickup_eta_minutes'] = $minutes;
            $pairs[$index]['sort_value'] = $route->durationSeconds;
            $pairs[$index]['recommendation_reason'] = $pairs[$index]['location_state'] === 'live'
                ? $minutes.' min from pickup'
                : 'Stale location · '.$minutes.' min from pickup (estimate)';
        }
    }
}
