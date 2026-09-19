<?php

namespace App\Services;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverLocation;
use App\Support\TaxiSettings;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Provider-neutral driver location foundation (Phase 12A.5).
 *
 * Single home for telemetry rules: eligibility, persistence, latest
 * resolution, freshness, scoping and retention. No GPS/maps dependency;
 * coordinates are stored as plain decimals ready for any future
 * provider (Google Maps / Mapbox) via TaxiDriverLocation::toMapPoint().
 *
 * Privacy model: an active linked driver may always share their own
 * position (operational readiness), but a ping is trip-linked ONLY when
 * the driver holds an open assignment on an operationally relevant
 * booking (assigned → on board). Coordinates never enter audit logs.
 */
class TaxiDriverLocationService
{
    /**
     * Booking states where live tracking is operationally relevant.
     *
     * @return array<int, string>
     */
    public static function trackedStatuses(): array
    {
        return [
            TaxiBookingStatus::DriverAssigned->value,
            TaxiBookingStatus::EnRoute->value,
            TaxiBookingStatus::Arrived->value,
            TaxiBookingStatus::PassengerOnBoard->value,
        ];
    }

    /**
     * Freshness states: live (within threshold), stale (older), offline
     * (no ping ever).
     */
    public function freshness(?TaxiDriverLocation $location, ?int $staleSeconds = null): string
    {
        if ($location === null || $location->captured_at === null) {
            return 'offline';
        }

        $threshold = $staleSeconds ?? $this->staleSeconds();

        return $location->captured_at->gte(now()->subSeconds($threshold)) ? 'live' : 'stale';
    }

    public function staleSeconds(): int
    {
        return max(30, (int) (TaxiSettings::get('taxi.tracking_stale_seconds') ?? 120));
    }

    public function retentionDays(): int
    {
        return max(1, (int) (TaxiSettings::get('taxi.location_retention_days') ?? 30));
    }

    /**
     * Persist a driver ping. Trip linkage is derived server-side from the
     * driver's current open assignment — request input never supplies
     * driver, vendor, booking or assignment ids.
     *
     * @param  array{latitude: float, longitude: float, accuracy_meters?: ?int, heading?: ?float, speed_kmh?: ?float, captured_at: Carbon, source?: string}  $data
     */
    public function record(Driver $driver, array $data): TaxiDriverLocation
    {
        if (! $driver->is_active || $driver->employment_status !== 'active') {
            throw ValidationException::withMessages(['location' => 'This driver account is not active.']);
        }

        $capturedAt = $data['captured_at'];

        if ($capturedAt->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['captured_at' => 'The reported time is in the future.']);
        }

        if ($capturedAt->lt(now()->subDay())) {
            throw ValidationException::withMessages(['captured_at' => 'The reported time is too old.']);
        }

        [$bookingId, $assignmentId] = $this->resolveTripLink($driver);

        return TaxiDriverLocation::create([
            'driver_id' => $driver->id,
            'taxi_booking_id' => $bookingId,
            'taxi_assignment_id' => $assignmentId,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'heading' => $data['heading'] ?? null,
            'speed_kmh' => $data['speed_kmh'] ?? null,
            'captured_at' => $capturedAt,
            'received_at' => now(),
            'source' => mb_substr((string) ($data['source'] ?? 'driver_portal'), 0, 20),
        ]);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function resolveTripLink(Driver $driver): array
    {
        $assignment = TaxiAssignment::with('booking:id,status')
            ->where('driver_id', $driver->id)
            ->whereNull('unassigned_at')
            ->latest('assigned_at')
            ->first();

        if ($assignment === null || $assignment->booking === null) {
            return [null, null];
        }

        if (! in_array($assignment->booking->status, self::trackedStatuses(), true)) {
            return [null, null];
        }

        return [$assignment->booking->id, $assignment->id];
    }

    public function latestFor(Driver $driver): ?TaxiDriverLocation
    {
        return $driver->latestLocation()->first();
    }

    /**
     * Operational row for tracking boards. Prefers already-eager-loaded
     * relations (vendorProfile, latestLocation, open assignments with
     * booking) so boards never fan out per driver.
     *
     * @return array{driver_id:int, driver_name:string, phone:?string, vendor:?string, availability_status:string, freshness:string, location:?array{latitude:string, longitude:string, accuracy_meters:?int, captured_at:?string, received_at:?string}, trip:?array{reference:string, status:string, pickup_at:?string, vehicle:?string}}
     */
    public function driverRow(Driver $driver): array
    {
        $location = $driver->relationLoaded('latestLocation')
            ? $driver->latestLocation
            : $driver->latestLocation()->first();

        $trip = null;

        $assignments = $driver->relationLoaded('assignments') ? $driver->assignments : null;

        if ($assignments !== null) {
            foreach ($assignments as $assignment) {
                $booking = $assignment->booking;

                if ($booking && in_array($booking->status, self::trackedStatuses(), true)) {
                    $trip = [
                        'booking_id' => $booking->id,
                        'reference' => $booking->reference,
                        'status' => $booking->status,
                        'pickup_at' => $booking->pickup_at?->toISOString(),
                        'vehicle' => $booking->assignedVehicle
                            ? $booking->assignedVehicle->name.' ('.$booking->assignedVehicle->registration_number.')'
                            : null,
                    ];
                    break;
                }
            }
        } else {
            $trip = $this->currentTrip($driver);
        }

        return [
            'driver_id' => $driver->id,
            'driver_name' => $driver->fullName(),
            'phone' => $driver->phone,
            'vendor' => $driver->relationLoaded('vendorProfile') ? $driver->vendorProfile?->business_name : $driver->vendorProfile?->business_name,
            'availability_status' => $driver->availability_status,
            'freshness' => $this->freshness($location),
            'location' => $location?->toMapPoint(),
            'trip' => $trip,
        ];
    }

    /**
     * @return array{reference:string, status:string, pickup_at:?string, vehicle:?string}|null
     */
    protected function currentTrip(Driver $driver): ?array
    {
        $booking = TaxiBooking::with('assignedVehicle:id,name,registration_number')
            ->whereHas('assignments', fn ($q) => $q->open()->where('driver_id', $driver->id))
            ->whereIn('status', self::trackedStatuses())
            ->orderBy('pickup_at')
            ->first();

        if ($booking === null) {
            return null;
        }

        return [
            'booking_id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status,
            'pickup_at' => $booking->pickup_at?->toISOString(),
            'vehicle' => $booking->assignedVehicle
                ? $booking->assignedVehicle->name.' ('.$booking->assignedVehicle->registration_number.')'
                : null,
        ];
    }

    /**
     * Delete raw pings older than the retention window. Booking records
     * are never touched — only telemetry is purged.
     */
    public function purgeExpired(?int $days = null): int
    {
        $cutoff = now()->subDays($days ?? $this->retentionDays());

        return TaxiDriverLocation::where('captured_at', '<', $cutoff)->delete();
    }
}
