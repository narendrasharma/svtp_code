<?php

namespace App\Services;

use App\Enums\TaxiBookingStatus;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingCancellation;
use App\Models\TaxiCancellationPolicy;
use App\Models\TaxiDriverEarning;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\TaxiSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxiCancellationService
{
    public const REASONS = ['customer_request', 'schedule_change', 'duplicate_booking', 'driver_unavailable', 'vehicle_issue', 'weather', 'operational_issue', 'no_show', 'other'];

    public function enabled(string $key): bool
    {
        return TaxiSettings::get('taxi.'.$key) === '1';
    }

    public function policy(TaxiBooking $booking): ?TaxiCancellationPolicy
    {
        return TaxiCancellationPolicy::where('is_active', true)
            ->where('currency', $booking->currency)
            ->where(fn ($q) => $q->whereNull('vendor_profile_id')->orWhere('vendor_profile_id', $booking->vendor_profile_id))
            ->where(fn ($q) => $q->whereNull('trip_type')->orWhere('trip_type', $booking->trip_type))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', now()))
            ->orderByRaw('vendor_profile_id IS NULL')->orderByRaw('trip_type IS NULL')->latest('id')->first();
    }

    public function assertEligible(TaxiBooking $booking, bool $noShow = false): void
    {
        if (! $this->enabled('cancellation.enabled')) {
            throw ValidationException::withMessages(['booking' => 'Taxi cancellation is disabled.']);
        }
        $target = $noShow ? TaxiBookingStatus::NoShow : TaxiBookingStatus::Cancelled;
        if (! $booking->status()->canTransitionTo($target)) {
            throw ValidationException::withMessages(['booking' => 'This booking cannot be cancelled in its current state.']);
        }
        if ($noShow && $booking->pickup_at->isFuture()) {
            throw ValidationException::withMessages(['booking' => 'No-show can only be recorded after pickup time.']);
        }
    }

    public function quote(TaxiBooking $booking, bool $noShow = false): array
    {
        $this->assertEligible($booking, $noShow);
        $payments = $booking->payments()->get();
        if ($payments->contains(fn ($payment) => $payment->currency !== $booking->currency)) {
            throw ValidationException::withMessages(['currency' => 'Payment currency does not match the booking.']);
        }
        $paid = number_format((float) $payments->sum('amount'), 2, '.', '');
        $total = (string) $booking->total_amount;
        $policy = $this->policy($booking);
        $minutes = now()->diffInMinutes($booking->pickup_at, false);
        $free = ! $noShow && $policy?->free_cancel_before_minutes !== null && $minutes >= $policy->free_cancel_before_minutes;
        $type = $noShow ? $policy?->no_show_fee_type : $policy?->fee_type;
        $value = (string) ($noShow ? ($policy?->no_show_fee_value ?? 0) : ($policy?->fee_value ?? 0));
        $fee = $free ? '0.00' : match ($type) {
            'percentage' => bcdiv(bcmul($total, $value, 4), '100', 2),
            'non_refundable' => $total,
            default => number_format((float) $value, 2, '.', ''),
        };
        if (! $free && $policy) {
            if (bccomp($fee, (string) $policy->minimum_fee, 2) < 0) {
                $fee = (string) $policy->minimum_fee;
            }
            if ($policy->maximum_fee !== null && bccomp($fee, (string) $policy->maximum_fee, 2) > 0) {
                $fee = (string) $policy->maximum_fee;
            }
        }
        if (bccomp($fee, $total, 2) > 0) {
            $fee = $total;
        }
        $refundable = bcsub($paid, $fee, 2);
        if (bccomp($refundable, '0', 2) < 0) {
            $refundable = '0.00';
        }

        return [
            'version' => 1, 'booking_total' => $total, 'amount_paid' => $paid,
            'cancellation_fee' => $fee, 'refundable_amount' => $refundable,
            'non_refundable_amount' => bcsub($paid, $refundable, 2),
            'currency' => $booking->currency,
            'policy' => $policy?->toArray(),
            'cutoff_state' => $noShow ? 'no_show' : ($free ? 'free' : 'fee_window'),
            'calculated_at' => now()->toISOString(),
            'driver_earning_review_required' => TaxiDriverEarning::where('taxi_booking_id', $booking->id)->exists(),
        ];
    }

    public function cancel(TaxiBooking $booking, string $reasonCode, ?string $reason, ?User $actor, string $actorType = 'admin'): TaxiBookingCancellation
    {
        return DB::transaction(function () use ($booking, $reasonCode, $reason, $actor, $actorType): TaxiBookingCancellation {
            $booking = TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! $this->enabled('cancellation.enabled')) {
                throw ValidationException::withMessages(['booking' => 'Taxi cancellation is disabled.']);
            }
            $existing = TaxiBookingCancellation::where('taxi_booking_id', $booking->id)->first();
            if ($existing) {
                return $existing;
            }
            if (! in_array($reasonCode, self::REASONS, true) || ($this->enabled('cancellation.default_reason_required') && trim($reason ?? '') === '')) {
                throw ValidationException::withMessages(['reason' => 'Select a valid reason and provide a cancellation note.']);
            }
            $noShow = $reasonCode === 'no_show';
            if ($actorType === 'customer' && ($noShow || ! $this->enabled('customer_cancellation.enabled'))) {
                throw ValidationException::withMessages(['booking' => 'Customer cancellation is not available.']);
            }
            $quote = $this->quote($booking, $noShow);
            $quote['reason_code'] = $reasonCode;
            $quote['reason'] = $reason;
            $driver = $booking->assignedDriver;
            $cancellation = TaxiBookingCancellation::create([
                'taxi_booking_id' => $booking->id, 'vendor_profile_id' => $booking->vendor_profile_id,
                'policy_id' => $quote['policy']['id'] ?? null, 'cancelled_by' => $actor?->id,
                'requested_by_type' => $actorType, 'reason_code' => $reasonCode, 'reason_text' => $reason,
                'status' => $noShow ? 'no_show' : 'cancelled', 'currency' => $booking->currency,
                'cancellation_fee' => $quote['cancellation_fee'], 'refundable_amount' => $quote['refundable_amount'],
                'calculation_snapshot' => $quote, 'cancelled_at' => now(),
            ]);
            $from = $booking->status;
            $booking->forceFill(['status' => $cancellation->status, 'cancelled_at' => now()])->save();
            $booking->statusHistories()->create(['from_status' => $from, 'to_status' => $cancellation->status, 'changed_by' => $actor?->id, 'note' => $reason]);
            app(TaxiAutoDispatchService::class)->stopForBooking($booking, $actor);
            app(TaxiBookingService::class)->unassign($booking, $actor, $reason);
            $this->notify($booking, 'taxi_cancelled', $driver?->user);

            return $cancellation;
        }, 3);
    }

    public function notify(TaxiBooking $booking, string $kind, ?User $driver = null, array $extra = []): void
    {
        $data = array_merge(['reference' => $booking->reference, 'taxi_booking_id' => $booking->id, 'pickup_at' => $booking->pickup_at->toDateTimeString()], $extra);
        foreach (collect([$booking->customer, $booking->vendorProfile?->user, $driver])->filter()->unique('id') as $user) {
            $user->notify(new CrmNotification($kind, $data));
        }
    }
}
