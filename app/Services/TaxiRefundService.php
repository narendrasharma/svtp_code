<?php

namespace App\Services;

use App\Enums\RefundStatus;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingCancellation;
use App\Models\TaxiRefund;
use App\Models\TaxiRefundItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxiRefundService
{
    public function summary(TaxiBooking $booking): array
    {
        $paid = number_format((float) $booking->payments()->sum('amount'), 2, '.', '');
        $processed = number_format((float) TaxiRefund::where('taxi_booking_id', $booking->id)->where('status', RefundStatus::Processed->value)->sum('amount'), 2, '.', '');
        $reserved = number_format((float) TaxiRefund::where('taxi_booking_id', $booking->id)->where('status', RefundStatus::Pending->value)->sum('amount'), 2, '.', '');
        $cancellation = TaxiBookingCancellation::where('taxi_booking_id', $booking->id)->first();
        $limit = $cancellation ? (string) $cancellation->refundable_amount : '0.00';
        if (bccomp($limit, $paid, 2) > 0) {
            $limit = $paid;
        }
        $remaining = bcsub(bcsub($limit, $processed, 2), $reserved, 2);
        $net = bcsub($paid, $processed, 2);
        $charge = $cancellation ? (string) $cancellation->cancellation_fee : (string) $booking->total_amount;

        return [
            'refunded' => (float) $processed, 'refund_pending' => (float) $reserved,
            'net_paid' => (float) $net, 'refundable_remaining' => max(0, (float) $remaining),
            'due' => max(0, (float) bcsub($charge, $net, 2)),
            'settled' => bccomp($charge, $net, 2) <= 0 && bccomp($remaining, '0', 2) <= 0 && bccomp($reserved, '0', 2) === 0,
        ];
    }

    public function create(TaxiBooking $booking, string $amount, string $requestKey, string $reason, User $actor): TaxiRefund
    {
        return DB::transaction(function () use ($booking, $amount, $requestKey, $reason, $actor): TaxiRefund {
            $booking = TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertEnabled();
            $existing = TaxiRefund::where('taxi_booking_id', $booking->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                return $existing;
            }
            $cancellation = TaxiBookingCancellation::where('taxi_booking_id', $booking->id)->first();
            $summary = $this->summary($booking);
            $amount = number_format((float) $amount, 2, '.', '');
            if (! $cancellation || bccomp($amount, '0', 2) <= 0 || bccomp($amount, (string) $summary['refundable_remaining'], 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Refund exceeds the remaining paid amount allowed by cancellation policy.']);
            }
            $payments = $booking->payments()->orderBy('id')->lockForUpdate()->get();
            if ($payments->contains(fn ($payment) => $payment->currency !== $booking->currency)) {
                throw ValidationException::withMessages(['currency' => 'Payment currency does not match the booking.']);
            }
            $refund = TaxiRefund::create([
                'refund_number' => app(NumberSeriesService::class)->next('taxi_refund'),
                'taxi_booking_id' => $booking->id, 'vendor_profile_id' => $booking->vendor_profile_id,
                'request_key' => $requestKey, 'currency' => $booking->currency, 'amount' => $amount,
                'status' => RefundStatus::Pending->value, 'reason' => $reason, 'requested_by' => $actor->id,
                'calculation_snapshot' => ['cancellation' => $cancellation->calculation_snapshot, 'remaining_before' => $summary['refundable_remaining'], 'amount' => $amount, 'calculated_at' => now()->toISOString()],
            ]);
            $remaining = $amount;
            foreach ($payments as $payment) {
                $allocated = TaxiRefundItem::where('taxi_payment_id', $payment->id)
                    ->whereIn('taxi_refund_id', TaxiRefund::select('id')->whereIn('status', ['pending', 'processed']))->sum('amount');
                $available = bcsub((string) $payment->amount, (string) $allocated, 2);
                $part = bccomp($available, $remaining, 2) > 0 ? $remaining : $available;
                if (bccomp($part, '0', 2) > 0) {
                    $refund->items()->create(['taxi_payment_id' => $payment->id, 'amount' => $part, 'payment_reference' => $payment->reference]);
                    $remaining = bcsub($remaining, $part, 2);
                }
                if (bccomp($remaining, '0', 2) === 0) {
                    break;
                }
            }
            if (bccomp($remaining, '0', 2) !== 0) {
                throw ValidationException::withMessages(['amount' => 'Insufficient unallocated payment balance.']);
            }

            return $refund;
        }, 3);
    }

    public function process(TaxiRefund $refund, string $method, string $reference, User $actor): TaxiRefund
    {
        return DB::transaction(function () use ($refund, $method, $reference, $actor): TaxiRefund {
            $booking = TaxiBooking::whereKey($refund->taxi_booking_id)->lockForUpdate()->firstOrFail();
            $refund = TaxiRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $this->assertEnabled();
            if ($refund->status === RefundStatus::Processed->value) {
                return $refund;
            }
            if ($refund->status !== RefundStatus::Pending->value) {
                throw ValidationException::withMessages(['refund' => 'Only pending refunds can be recorded as refunded.']);
            }
            if (trim($reference) === '' || ! in_array($method, array_column(\App\Enums\PaymentMethod::cases(), 'value'), true)) {
                throw ValidationException::withMessages(['reference' => 'A valid method and external refund reference are required.']);
            }
            $refund->update(['status' => RefundStatus::Processed->value, 'method' => $method, 'reference' => $reference, 'processed_by' => $actor->id, 'refunded_at' => now()]);
            app(TaxiCancellationService::class)->notify($booking, 'taxi_refunded', null, ['amount' => $refund->amount, 'currency' => $refund->currency]);

            return $refund->refresh();
        }, 3);
    }

    private function assertEnabled(): void
    {
        if (! app(TaxiCancellationService::class)->enabled('refunds.enabled')) {
            throw ValidationException::withMessages(['refund' => 'Taxi refunds are disabled.']);
        }
    }
}
