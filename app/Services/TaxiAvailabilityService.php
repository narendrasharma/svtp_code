<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Phase 12A.3: reusable manual-dispatch eligibility layer.
 *
 * Answers whether a driver/vehicle may take a booking at its pickup
 * time. No GPS, no maps, no auto-dispatch — manual desk checks only.
 * TaxiBookingService::assign() remains authoritative; this service
 * exposes the same rules for board filtering without duplicating
 * business logic into controllers.
 */
class TaxiAvailabilityService
{
    /**
     * Active ride states that block a second concurrent assignment.
     *
     * @return array<int, string>
     */
    public static function activeTripStatuses(): array
    {
        return ['driver_assigned', 'en_route', 'arrived', 'passenger_on_board'];
    }

    /**
     * @return array{eligible: bool, reason: ?string}
     */
    public function checkDriver(Driver $driver, TaxiBooking $booking): array
    {
        if ($booking->vendor_profile_id !== null && (int) $driver->vendor_profile_id !== (int) $booking->vendor_profile_id) {
            return ['eligible' => false, 'reason' => 'Driver belongs to a different vendor.'];
        }

        if (! $driver->is_active || $driver->employment_status !== 'active') {
            return ['eligible' => false, 'reason' => 'Driver is not active.'];
        }

        if ($driver->availability_status !== 'available') {
            return ['eligible' => false, 'reason' => 'Driver is not available.'];
        }

        if ($booking->pickup_at !== null && $driver->isOnLeaveAt($booking->pickup_at)) {
            return ['eligible' => false, 'reason' => 'Driver is on leave at the pickup time.'];
        }

        if ($this->driverHasOverlappingTrip($driver, $booking)) {
            return ['eligible' => false, 'reason' => 'Driver already has an active trip assignment.'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * @return array{eligible: bool, reason: ?string}
     */
    public function checkVehicle(Vehicle $vehicle, TaxiBooking $booking): array
    {
        if ($booking->vendor_profile_id !== null && (int) $vehicle->vendor_profile_id !== (int) $booking->vendor_profile_id) {
            return ['eligible' => false, 'reason' => 'Vehicle belongs to a different vendor.'];
        }

        if (! $vehicle->is_active) {
            return ['eligible' => false, 'reason' => 'Vehicle is not active.'];
        }

        if (in_array($vehicle->status, ['maintenance', 'out_of_service'], true)) {
            return ['eligible' => false, 'reason' => 'Vehicle is '.$vehicle->status.' and cannot be dispatched.'];
        }

        if ($booking->pickup_at !== null && $vehicle->isBlockedAt($booking->pickup_at)) {
            return ['eligible' => false, 'reason' => 'Vehicle is blocked at the pickup time.'];
        }

        if ((int) $vehicle->passenger_capacity < (int) $booking->passenger_count) {
            return ['eligible' => false, 'reason' => 'Vehicle seats fewer passengers than booked.'];
        }

        if ($this->vehicleHasOverlappingTrip($vehicle, $booking)) {
            return ['eligible' => false, 'reason' => 'Vehicle already has an active trip assignment.'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * @return array{eligible: bool, driver_reason: ?string, vehicle_reason: ?string}
     */
    public function checkPair(Driver $driver, Vehicle $vehicle, TaxiBooking $booking): array
    {
        $driverCheck = $this->checkDriver($driver, $booking);
        $vehicleCheck = $this->checkVehicle($vehicle, $booking);

        return [
            'eligible' => $driverCheck['eligible'] && $vehicleCheck['eligible'],
            'driver_reason' => $driverCheck['reason'],
            'vehicle_reason' => $vehicleCheck['reason'],
        ];
    }

    /**
     * Eligible drivers for a booking's vendor, annotated with reasons.
     *
     * @return Collection<int, array{driver: Driver, eligible: bool, reason: ?string}>
     */
    public function eligibleDrivers(TaxiBooking $booking, int $limit = 100): Collection
    {
        if ($booking->vendor_profile_id === null) {
            return collect();
        }

        return Driver::where('vendor_profile_id', $booking->vendor_profile_id)
            ->orderBy('first_name')
            ->limit($limit)
            ->get()
            ->map(fn (Driver $driver): array => [
                'driver' => $driver,
                ...$this->checkDriver($driver, $booking),
            ]);
    }

    /**
     * Eligible vehicles for a booking's vendor, annotated with reasons.
     *
     * @return Collection<int, array{vehicle: Vehicle, eligible: bool, reason: ?string}>
     */
    public function eligibleVehicles(TaxiBooking $booking, int $limit = 100): Collection
    {
        if ($booking->vendor_profile_id === null) {
            return collect();
        }

        return Vehicle::where('vendor_profile_id', $booking->vendor_profile_id)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'vehicle' => $vehicle,
                ...$this->checkVehicle($vehicle, $booking),
            ]);
    }

    public function hasOverlappingTrip(Driver $driver, Vehicle $vehicle, TaxiBooking $booking): bool
    {
        return $this->driverHasOverlappingTrip($driver, $booking)
            || $this->vehicleHasOverlappingTrip($vehicle, $booking);
    }

    protected function driverHasOverlappingTrip(Driver $driver, TaxiBooking $booking): bool
    {
        return TaxiBooking::where('id', '!=', $booking->id)
            ->whereIn('status', self::activeTripStatuses())
            ->where('assigned_driver_id', $driver->id)
            ->exists();
    }

    protected function vehicleHasOverlappingTrip(Vehicle $vehicle, TaxiBooking $booking): bool
    {
        return TaxiBooking::where('id', '!=', $booking->id)
            ->whereIn('status', self::activeTripStatuses())
            ->where('assigned_vehicle_id', $vehicle->id)
            ->exists();
    }
}
