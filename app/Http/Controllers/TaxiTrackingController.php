<?php

namespace App\Http\Controllers;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\TaxiTrackingToken;
use App\Services\TaxiDriverLocationService;
use App\Services\TaxiRouteService;
use App\Services\TaxiTrackingTokenService;
use App\Support\TaxiSettings;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public customer trip tracking (Phase 12A.9, no login).
 *
 * Token-authenticated only: the unguessable token resolves the booking,
 * and every response is an explicit customer-safe DTO — never a model.
 * Driver coordinates appear only while the trip is in an active,
 * customer-visible status with the CURRENT assignment; reassignment
 * and terminal states cut exposure automatically.
 */
class TaxiTrackingController extends Controller
{
    /**
     * Statuses where the customer may see the live driver position.
     *
     * @return array<int, string>
     */
    public static function visibleStatuses(): array
    {
        return [
            TaxiBookingStatus::EnRoute->value,
            TaxiBookingStatus::Arrived->value,
            TaxiBookingStatus::PassengerOnBoard->value,
        ];
    }

    public function __construct(
        protected TaxiTrackingTokenService $tokens,
        protected TaxiDriverLocationService $tracking,
        protected TaxiRouteService $routes,
    ) {}

    protected function resolve(string $token): TaxiTrackingToken
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{20,128}$/', $token) === 1, 404);

        $record = $this->tokens->resolve($token);

        abort_unless($record !== null, 404);

        return $record;
    }

    public function show(string $token): Response
    {
        $record = $this->resolve($token);

        return Inertia::render('Taxi/Track/Show', $this->payload($record));
    }

    public function status(string $token): JsonResponse
    {
        $record = $this->resolve($token);

        return response()->json($this->payload($record));
    }

    /**
     * Explicit customer-safe payload. No models, no ids, no financials,
     * no internal notes, no vendor/admin data.
     *
     * @return array<string, mixed>
     */
    public function payload(TaxiTrackingToken $record): array
    {
        /** @var TaxiBooking $booking */
        $booking = $record->booking()->firstOrFail()->fresh();

        $driver = $booking->assigned_driver_id ? Driver::find($booking->assigned_driver_id) : null;
        $location = ($driver && $this->locationVisible($booking)) ? $this->tracking->latestFor($driver) : null;

        $showRoute = TaxiSettings::enabled('taxi.customer_tracking.show_route');
        $route = ($location !== null && $showRoute) ? $this->routes->routeForBooking($booking, $location) : null;

        return [
            'reference' => $booking->reference,
            'status' => $booking->status,
            'status_label' => $booking->status()->label(),
            'trip_type' => $booking->trip_type,
            'pickup_at' => $booking->pickup_at?->toISOString(),
            'pickup_address' => $booking->pickup_address,
            'drop_address' => $booking->drop_address,
            'driver' => $driver && $booking->status !== TaxiBookingStatus::Confirmed->value ? [
                'display_name' => $this->displayName($driver),
                'phone' => TaxiSettings::enabled('taxi.customer_tracking.show_driver_phone') ? $driver->phone : null,
                'vehicle' => $booking->assignedVehicle ? [
                    'type' => $booking->assignedVehicle->vehicleType?->name,
                    'make_model' => trim(($booking->assignedVehicle->make ?? '').' '.($booking->assignedVehicle->model ?? '')) ?: null,
                    'registration' => TaxiSettings::enabled('taxi.customer_tracking.show_vehicle_registration')
                        ? $booking->assignedVehicle->registration_number
                        : null,
                ] : null,
            ] : null,
            'tracking' => [
                'state' => $this->trackingState($booking, $location),
                'location' => $location?->toMapPoint(),
                'updated_at' => $location?->captured_at?->toISOString(),
            ],
            'route' => $route?->toArray(),
            'map' => $this->routes->browserMapConfig(),
            'refresh_seconds' => min(60, max(15, (int) (TaxiSettings::get('taxi.customer_tracking.refresh_seconds') ?? 30))),
        ];
    }

    protected function locationVisible(TaxiBooking $booking): bool
    {
        return $booking->assigned_driver_id !== null
            && in_array($booking->status, self::visibleStatuses(), true);
    }

    protected function trackingState(TaxiBooking $booking, $location): string
    {
        if ($booking->status()->isTerminal()) {
            return 'ended';
        }

        if (! $this->locationVisible($booking)) {
            return 'hidden';
        }

        return $this->tracking->freshness($location);
    }

    protected function displayName(Driver $driver): string
    {
        $initial = $driver->last_name !== null && trim($driver->last_name) !== ''
            ? ' '.mb_substr(trim($driver->last_name), 0, 1).'.'
            : '';

        return trim($driver->first_name.$initial);
    }
}
