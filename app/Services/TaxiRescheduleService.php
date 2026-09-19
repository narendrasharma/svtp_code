<?php

namespace App\Services;

use App\Models\TaxiBooking;
use App\Models\TaxiBookingReschedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxiRescheduleService
{
    public function quote(TaxiBooking $booking, string $pickupAt, ?string $returnAt): array
    {
        if (! app(TaxiCancellationService::class)->enabled('reschedule.enabled') || ! in_array($booking->status, ['draft', 'quoted', 'confirmed', 'driver_assigned'], true)) {
            throw ValidationException::withMessages(['pickup_at' => 'This booking cannot be rescheduled.']);
        }
        $pickup = Carbon::parse($pickupAt);
        $return = $returnAt ? Carbon::parse($returnAt) : null;
        $minutes = (int) \App\Support\TaxiSettings::get('taxi.min_advance_minutes');
        if ($pickup->lte(now()->addMinutes($minutes)) || ($return && $return->lte($pickup)) || ($booking->return_at && ! $return)) {
            throw ValidationException::withMessages(['pickup_at' => 'Choose a future pickup and a return after pickup when applicable.']);
        }
        $maxDays = (int) \App\Support\TaxiSettings::get('taxi.max_advance_days');
        if ($maxDays > 0 && $pickup->gt(now()->addDays($maxDays))) {
            throw ValidationException::withMessages(['pickup_at' => 'Pickup exceeds the advance booking window.']);
        }
        $candidate = clone $booking;
        $candidate->pickup_at = $pickup;
        $candidate->return_at = $return;
        $driver = $booking->assignedDriver;
        $vehicle = $booking->assignedVehicle;
        $retain = $driver && $vehicle && app(TaxiAvailabilityService::class)->checkPair($driver, $vehicle, $candidate)['eligible'];

        return ['pickup_at' => $pickup->toDateTimeString(), 'return_at' => $return?->toDateTimeString(), 'assignment_impact' => $driver ? ($retain ? 'retained' : 'released') : 'unassigned', 'pricing_impact' => 'Agreed price preserved; no automatic repricing.', 'total_amount' => $booking->total_amount, 'currency' => $booking->currency];
    }

    public function reschedule(TaxiBooking $booking, string $pickupAt, ?string $returnAt, string $reason, User $actor): ?TaxiBookingReschedule
    {
        return DB::transaction(function () use ($booking, $pickupAt, $returnAt, $reason, $actor): ?TaxiBookingReschedule {
            $booking = TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $quote = $this->quote($booking, $pickupAt, $returnAt);
            if ($booking->pickup_at->toDateTimeString() === $quote['pickup_at'] && $booking->return_at?->toDateTimeString() === $quote['return_at']) {
                return null;
            }
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A reschedule reason is required.']);
            }
            $driver = $booking->assignedDriver;
            $history = TaxiBookingReschedule::create([
                'taxi_booking_id' => $booking->id, 'actor_id' => $actor->id,
                'old_pickup_at' => $booking->pickup_at, 'old_return_at' => $booking->return_at,
                'new_pickup_at' => $quote['pickup_at'], 'new_return_at' => $quote['return_at'], 'reason' => $reason,
                'snapshot' => $quote,
            ]);
            app(TaxiAutoDispatchService::class)->stopForBooking($booking, $actor);
            if ($quote['assignment_impact'] === 'released') {
                app(TaxiBookingService::class)->unassign($booking, $actor, $reason);
            }
            $booking->forceFill(['pickup_at' => $quote['pickup_at'], 'return_at' => $quote['return_at']])->save();
            app(TaxiCancellationService::class)->notify($booking, 'taxi_rescheduled', $driver?->user);

            return $history;
        }, 3);
    }
}
