<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TaxiBookingStatus;
use App\Models\TaxiBooking;
use App\Models\TaxiPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Taxi manual payment collection (decision B).
 *
 * Mirrors the tour BookingPayment pattern (append-only rows, derived
 * due) without touching tour finance. Unification deferred until a
 * second taxi phase proves the shared shape.
 */
class TaxiPaymentService
{
    /**
     * @return array{total: float, paid: float, due: float, currency: string, payments_count: int}
     */
    public function summary(TaxiBooking $booking): array
    {
        return app(TaxiBookingService::class)->summary($booking);
    }

    public function recordPayment(
        TaxiBooking $booking,
        float $amount,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $note = null,
        ?string $externalReference = null,
        ?User $receivedBy = null,
    ): TaxiPayment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        if (in_array($booking->status, [TaxiBookingStatus::Cancelled->value, TaxiBookingStatus::NoShow->value], true)) {
            throw ValidationException::withMessages(['booking' => 'Cancelled bookings cannot receive payments.']);
        }

        return DB::transaction(function () use ($booking, $amount, $method, $actor, $note, $externalReference, $receivedBy): TaxiPayment {
            $summary = $this->summary($booking->refresh());

            if ($summary['due'] <= 0) {
                throw ValidationException::withMessages(['amount' => 'This booking has no outstanding balance.']);
            }

            if ($amount > $summary['due']) {
                throw ValidationException::withMessages([
                    'amount' => 'Overpayment is blocked: the outstanding balance is ₹'.number_format($summary['due'], 2).'.',
                ]);
            }

            // Receipt reference derives from the booking reference (no
            // separate series consumed): TX-2026-000123-P01, -P02, ...
            $seq = $summary['payments_count'] + 1;
            $reference = $booking->reference.'-P'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

            while (TaxiPayment::where('reference', $reference)->exists()) {
                $seq++;
                $reference = $booking->reference.'-P'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
            }

            $payment = $booking->payments()->create([
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $summary['currency'],
                'payment_method' => $method->value,
                'paid_at' => now(),
                'received_by' => $receivedBy?->id ?? $actor?->id,
                'external_reference' => $externalReference,
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
                'created_by' => $actor?->id,
            ]);

            $after = $this->summary($booking->refresh());

            $booking->forceFill([
                'payment_status' => $after['due'] <= 0 ? PaymentStatus::Paid->value : ($after['paid'] > 0 ? PaymentStatus::PartiallyPaid->value : PaymentStatus::Unpaid->value),
            ])->save();

            return $payment->refresh();
        });
    }
}
