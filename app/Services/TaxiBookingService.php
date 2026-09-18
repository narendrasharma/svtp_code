<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\PaymentStatus;
use App\Enums\TaxiBookingStatus;
use App\Enums\TripType;
use App\Models\Driver;
use App\Models\Quotation;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\CrmNotification;
use App\Support\TaxiSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Taxi booking domain (12A.1): One-Way + Airport Transfer.
 *
 * Server-side state machine — the frontend never decides transitions.
 * Pricing is manual quoted totals recomputed server-side
 * (base + extra − discount + tax); no pricing engine yet.
 */
class TaxiBookingService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): TaxiBooking
    {
        $tripType = $data['trip_type'] ?? TripType::OneWay->value;

        if (! in_array($tripType, TripType::bookable(), true)) {
            throw ValidationException::withMessages(['trip_type' => 'This trip type is not bookable yet.']);
        }

        if ($tripType === TripType::OneWay->value && ! TaxiSettings::enabled('taxi.one_way_enabled')) {
            throw ValidationException::withMessages(['trip_type' => 'One-way bookings are currently disabled.']);
        }

        if ($tripType === TripType::AirportTransfer->value && ! TaxiSettings::enabled('taxi.airport_transfer_enabled')) {
            throw ValidationException::withMessages(['trip_type' => 'Airport transfers are currently disabled.']);
        }

        if (! TaxiSettings::enabled('taxi.booking_enabled')) {
            throw ValidationException::withMessages(['booking' => 'Taxi bookings are currently disabled.']);
        }

        $pickupAt = $this->parsePickupAt($data['pickup_at'] ?? null);
        $this->assertAdvanceWindow($pickupAt);

        $source = isset($data['source']) ? BookingSource::from($data['source']) : BookingSource::Admin;

        if (empty($data['customer_user_id']) && ! TaxiSettings::enabled('taxi.allow_guest_booking')) {
            throw ValidationException::withMessages(['customer' => 'Guest bookings are disabled; link a customer account.']);
        }

        $totals = $this->totals($data);

        return DB::transaction(function () use ($data, $actor, $tripType, $pickupAt, $source, $totals): TaxiBooking {
            $booking = TaxiBooking::create([
                'reference' => app(NumberSeriesService::class)->next('taxi_ride'),
                'customer_user_id' => $data['customer_user_id'] ?? null,
                'vendor_profile_id' => $data['vendor_profile_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'trip_type' => $tripType,
                'pickup_at' => $pickupAt,
                'pickup_address' => trim((string) ($data['pickup_address'] ?? '')),
                'pickup_lat' => $data['pickup_lat'] ?? null,
                'pickup_lng' => $data['pickup_lng'] ?? null,
                'drop_address' => trim((string) ($data['drop_address'] ?? '')),
                'drop_lat' => $data['drop_lat'] ?? null,
                'drop_lng' => $data['drop_lng'] ?? null,
                'airport_direction' => $tripType === TripType::AirportTransfer->value ? ($data['airport_direction'] ?? null) : null,
                'flight_number' => $tripType === TripType::AirportTransfer->value ? ($data['flight_number'] ?? null) : null,
                'airline' => $tripType === TripType::AirportTransfer->value ? ($data['airline'] ?? null) : null,
                'terminal' => $tripType === TripType::AirportTransfer->value ? ($data['terminal'] ?? null) : null,
                'passenger_count' => max(1, (int) ($data['passenger_count'] ?? 1)),
                'luggage_count' => isset($data['luggage_count']) ? max(0, (int) $data['luggage_count']) : null,
                'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
                'customer_name' => trim((string) ($data['customer_name'] ?? '')),
                'customer_phone' => trim((string) ($data['customer_phone'] ?? '')),
                'customer_email' => $data['customer_email'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null,
                'source' => $source->value,
                'status' => TaxiBookingStatus::Confirmed->value,
                'payment_status' => PaymentStatus::Unpaid->value,
                'currency' => $data['currency'] ?? TaxiSettings::get('taxi.default_currency') ?? 'INR',
                ...$totals,
                'quoted_distance_km' => $data['quoted_distance_km'] ?? null,
                'quoted_duration_minutes' => $data['quoted_duration_minutes'] ?? null,
                'confirmed_at' => now(),
                'created_by' => $actor?->id,
            ]);

            if (! empty($data['stops']) && is_array($data['stops'])) {
                foreach (array_values($data['stops']) as $i => $stop) {
                    if (empty($stop['address'])) {
                        continue;
                    }

                    $booking->stops()->create([
                        'stop_type' => $stop['stop_type'] ?? 'intermediate',
                        'address' => mb_substr(trim((string) $stop['address']), 0, 500),
                        'lat' => $stop['lat'] ?? null,
                        'lng' => $stop['lng'] ?? null,
                        'notes' => isset($stop['notes']) ? mb_substr((string) $stop['notes'], 0, 255) : null,
                        'sort_order' => $i,
                    ]);
                }
            }

            $booking->statusHistories()->create([
                'from_status' => null,
                'to_status' => TaxiBookingStatus::Confirmed->value,
                'changed_by' => $actor?->id,
                'note' => 'Taxi booking created.',
            ]);

            $this->notifyCustomer($booking->refresh(), 'taxi_booking_confirmed');

            return $booking->refresh();
        });
    }

    /**
     * Convert an accepted taxi quotation into a taxi booking. Trip fields
     * come from the conversion payload (validated here); customer/lead
     * and quoted totals map from the quotation. Tour conversion untouched.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromQuotation(Quotation $quotation, array $data, ?User $actor = null): TaxiBooking
    {
        if ($quotation->service_type !== 'taxi') {
            throw ValidationException::withMessages(['quotation' => 'Only taxi quotations convert to taxi bookings.']);
        }

        if ($quotation->status !== 'accepted') {
            throw ValidationException::withMessages(['quotation' => 'Only accepted quotations can be converted.']);
        }

        return $this->create(array_merge($data, [
            'customer_user_id' => $data['customer_user_id'] ?? $quotation->customer_user_id,
            'lead_id' => $data['lead_id'] ?? $quotation->lead_id,
            'quotation_id' => $quotation->id,
            'customer_name' => $data['customer_name'] ?? $quotation->lead?->name ?? $quotation->customer?->name ?? 'Taxi Guest',
            'customer_phone' => $data['customer_phone'] ?? $quotation->lead?->phone ?? '',
            'customer_email' => $data['customer_email'] ?? $quotation->lead?->email ?? $quotation->customer?->email,
            'base_amount' => $data['base_amount'] ?? (float) $quotation->total_amount,
            'source' => $data['source'] ?? BookingSource::Quotation->value,
        ]), $actor);
    }

    public function changeStatus(TaxiBooking $booking, TaxiBookingStatus $to, ?User $actor = null, ?string $note = null): TaxiBooking
    {
        $from = $booking->status();

        if ($from === $to) {
            return $booking;
        }

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move taxi booking from {$from->label()} to {$to->label()}.",
            ]);
        }

        return DB::transaction(function () use ($booking, $from, $to, $actor, $note): TaxiBooking {
            $booking->update([
                'status' => $to->value,
                'completed_at' => $to === TaxiBookingStatus::Completed ? now() : $booking->completed_at,
                'cancelled_at' => in_array($to, [TaxiBookingStatus::Cancelled, TaxiBookingStatus::NoShow], true) ? now() : $booking->cancelled_at,
            ]);

            $booking->statusHistories()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $actor?->id,
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ]);

            return $booking->refresh();
        });
    }

    /**
     * Assign driver + vehicle. Same-vendor, active, available, capacity
     * and overlap checks. History is authoritative; booking FKs are
     * convenience pointers.
     */
    public function assign(TaxiBooking $booking, Driver $driver, Vehicle $vehicle, ?User $actor = null, ?string $note = null): TaxiAssignment
    {
        if ($booking->status()->isTerminal()) {
            throw ValidationException::withMessages(['assignment' => 'Terminal bookings cannot be assigned.']);
        }

        if ($booking->vendor_profile_id === null) {
            throw ValidationException::withMessages(['assignment' => 'Assign a vendor to the booking before assigning fleet.']);
        }

        if ((int) $driver->vendor_profile_id !== (int) $booking->vendor_profile_id) {
            throw ValidationException::withMessages(['driver_id' => 'Driver belongs to a different vendor.']);
        }

        if ((int) $vehicle->vendor_profile_id !== (int) $booking->vendor_profile_id) {
            throw ValidationException::withMessages(['vehicle_id' => 'Vehicle belongs to a different vendor.']);
        }

        if (! $driver->is_active || $driver->employment_status !== 'active') {
            throw ValidationException::withMessages(['driver_id' => 'Driver is not active.']);
        }

        if ($driver->availability_status !== 'available') {
            throw ValidationException::withMessages(['driver_id' => 'Driver is not available ('.$driver->availability()->label().').']);
        }

        if ($driver->isOnLeaveAt($booking->pickup_at)) {
            throw ValidationException::withMessages(['driver_id' => 'Driver is on leave at the pickup time.']);
        }

        if (! $vehicle->is_active) {
            throw ValidationException::withMessages(['vehicle_id' => 'Vehicle is not active.']);
        }

        if (in_array($vehicle->status, ['maintenance', 'out_of_service'], true)) {
            throw ValidationException::withMessages(['vehicle_id' => 'Vehicle is '.$vehicle->status()->label().'.']);
        }

        if ($vehicle->isBlockedAt($booking->pickup_at)) {
            throw ValidationException::withMessages(['vehicle_id' => 'Vehicle is blocked at the pickup time.']);
        }

        if ((int) $vehicle->passenger_capacity < (int) $booking->passenger_count) {
            throw ValidationException::withMessages(['vehicle_id' => 'Vehicle seats fewer passengers than booked.']);
        }

        if ($this->hasOverlappingTrip($driver, $vehicle, $booking)) {
            throw ValidationException::withMessages(['assignment' => 'Driver or vehicle already has an active trip assignment.']);
        }

        return DB::transaction(function () use ($booking, $driver, $vehicle, $actor, $note): TaxiAssignment {
            $booking->assignments()->open()->update(['unassigned_at' => now()]);

            $assignment = $booking->assignments()->create([
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_by' => $actor?->id,
                'assigned_at' => now(),
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ]);

            $vehicle->update(['status' => 'assigned']);

            $booking->forceFill([
                'assigned_driver_id' => $driver->id,
                'assigned_vehicle_id' => $vehicle->id,
            ])->save();

            if ($booking->status === TaxiBookingStatus::Confirmed->value) {
                $this->changeStatus($booking->refresh(), TaxiBookingStatus::DriverAssigned, $actor, 'Driver assigned.');
            }

            $this->notifyCustomer($booking->refresh(), 'taxi_driver_assigned');

            if ($driver->user) {
                $driver->user->notify(new CrmNotification('taxi_assignment', [
                    'taxi_booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'pickup_at' => $booking->pickup_at->toDateTimeString(),
                    'pickup_address' => $booking->pickup_address,
                ]));
            }

            return $assignment;
        });
    }

    public function unassign(TaxiBooking $booking, ?User $actor = null, ?string $note = null): TaxiBooking
    {
        return DB::transaction(function () use ($booking, $actor, $note): TaxiBooking {
            $open = $booking->assignments()->open()->latest('assigned_at')->first();

            if ($open) {
                $open->update(['unassigned_at' => now()]);
            }

            if ($booking->assignedVehicle) {
                $booking->assignedVehicle->update(['status' => 'available']);
            }

            $booking->forceFill(['assigned_driver_id' => null, 'assigned_vehicle_id' => null])->save();

            if ($booking->status === TaxiBookingStatus::DriverAssigned->value) {
                $this->changeStatus($booking->refresh(), TaxiBookingStatus::Confirmed, $actor, $note ?? 'Assignment removed.');
            }

            return $booking->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{base_amount: float, extra_amount: float, discount_amount: float, tax_amount: float, total_amount: float}
     */
    public function totals(array $data): array
    {
        $base = max(0, (float) ($data['base_amount'] ?? $data['total_amount'] ?? 0));
        $extra = max(0, (float) ($data['extra_amount'] ?? 0));
        $discount = min($base + $extra, max(0, (float) ($data['discount_amount'] ?? 0)));
        $tax = max(0, (float) ($data['tax_amount'] ?? 0));

        if ($base <= 0) {
            throw ValidationException::withMessages(['base_amount' => 'A quoted price greater than zero is required.']);
        }

        return [
            'base_amount' => round($base, 2),
            'extra_amount' => round($extra, 2),
            'discount_amount' => round($discount, 2),
            'tax_amount' => round($tax, 2),
            'total_amount' => round($base + $extra - $discount + $tax, 2),
        ];
    }

    /**
     * @return array{total: float, paid: float, due: float, currency: string, payments_count: int}
     */
    public function summary(TaxiBooking $booking): array
    {
        $paid = round((float) $booking->payments()->sum('amount'), 2);
        $total = round((float) $booking->total_amount, 2);

        return [
            'total' => $total,
            'paid' => $paid,
            'due' => round($total - $paid, 2),
            'currency' => $booking->currency ?? 'INR',
            'payments_count' => $booking->payments()->count(),
        ];
    }

    protected function parsePickupAt(mixed $value): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages(['pickup_at' => 'Pickup date and time is required.']);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['pickup_at' => 'Pickup date and time is invalid.']);
        }
    }

    protected function assertAdvanceWindow(Carbon $pickupAt): void
    {
        $minMinutes = TaxiSettings::minAdvanceMinutes();

        if ($pickupAt->lt(now()->addMinutes($minMinutes))) {
            throw ValidationException::withMessages([
                'pickup_at' => "Pickup must be at least {$minMinutes} minutes in advance.",
            ]);
        }

        $maxDays = TaxiSettings::maxAdvanceDays();

        if ($maxDays !== null && $pickupAt->gt(now()->addDays($maxDays))) {
            throw ValidationException::withMessages([
                'pickup_at' => "Pickup cannot be more than {$maxDays} days ahead.",
            ]);
        }
    }

    protected function hasOverlappingTrip(Driver $driver, Vehicle $vehicle, TaxiBooking $booking): bool
    {
        $active = ['driver_assigned', 'en_route', 'arrived', 'passenger_on_board'];

        return TaxiBooking::where('id', '!=', $booking->id)
            ->whereIn('status', $active)
            ->where(function ($query) use ($driver, $vehicle): void {
                $query->where('assigned_driver_id', $driver->id)
                    ->orWhere('assigned_vehicle_id', $vehicle->id);
            })
            ->exists();
    }

    protected function notifyCustomer(TaxiBooking $booking, string $kind): void
    {
        if (! $booking->customer) {
            return;
        }

        $booking->customer->notify(new CrmNotification($kind, [
            'taxi_booking_id' => $booking->id,
            'reference' => $booking->reference,
            'pickup_at' => $booking->pickup_at->toDateTimeString(),
            'pickup_address' => $booking->pickup_address,
            'drop_address' => $booking->drop_address,
            'driver' => $booking->assignedDriver?->fullName(),
            'vehicle' => $booking->assignedVehicle ? $booking->assignedVehicle->name.' ('.$booking->assignedVehicle->registration_number.')' : null,
            'total' => number_format((float) $booking->total_amount, 2),
        ]));
    }
}
