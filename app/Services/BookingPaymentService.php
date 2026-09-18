<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual/partial payment collection. Payments are append-only rows; the
 * outstanding balance is always derived (total − paid + refunded) and
 * never stored. No ledger movement happens here — the existing
 * markPayment(Paid) hook still owns the single vendor-earning credit.
 */
class BookingPaymentService
{
    /**
     * @return array{total: float, paid: float, refunded: float, due: float, currency: string, payments_count: int}
     */
    public function summary(Booking $booking): array
    {
        $paid = round((float) $booking->payments()->sum('amount'), 2);
        $refunded = round((float) $booking->refunds()->where('status', 'processed')->sum('amount'), 2);
        $total = round((float) $booking->total_amount, 2);

        return [
            'total' => $total,
            'paid' => $paid,
            'refunded' => $refunded,
            'due' => round($total - $paid + $refunded, 2),
            'currency' => $booking->currency ?? 'INR',
            'payments_count' => $booking->payments()->count(),
        ];
    }

    public function recordPayment(
        Booking $booking,
        float $amount,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $note = null,
        ?string $externalReference = null,
        ?User $receivedBy = null,
    ): BookingPayment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        if ($booking->booking_status->value === 'cancelled') {
            throw ValidationException::withMessages(['booking' => 'Cancelled bookings cannot receive payments. Record a new booking instead.']);
        }

        return DB::transaction(function () use ($booking, $amount, $method, $actor, $note, $externalReference, $receivedBy): BookingPayment {
            $summary = $this->summary($booking->refresh());

            if ($summary['due'] <= 0) {
                throw ValidationException::withMessages(['amount' => 'This booking has no outstanding balance.']);
            }

            if ($amount > $summary['due']) {
                throw ValidationException::withMessages([
                    'amount' => 'Overpayment is blocked: the outstanding balance is ₹'.number_format($summary['due'], 2).'. Record ₹'.number_format($summary['due'], 2).' or less.',
                ]);
            }

            $payment = $booking->payments()->create([
                'reference' => app(NumberSeriesService::class)->next('payment'),
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
            $target = $after['due'] <= 0 ? PaymentStatus::Paid : ($after['paid'] > 0 ? PaymentStatus::PartiallyPaid : PaymentStatus::Unpaid);

            app(BookingService::class)->markPayment(
                $booking->refresh(),
                $target,
                $actor,
                "Payment {$payment->reference} ₹".number_format($amount, 2)." via {$method->label()}. Outstanding ₹".number_format($after['due'], 2).'.'
            );

            if ($booking->user) {
                $booking->user->notify(new CrmNotification('payment_recorded', [
                    'booking_id' => $booking->id,
                    'reference' => $booking->booking_reference_id,
                    'payment_reference' => $payment->reference,
                    'amount' => number_format($amount, 2),
                    'due' => number_format($after['due'], 2),
                ]));
            }

            return $payment->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptData(BookingPayment $payment): array
    {
        $payment->loadMissing(['booking.package:id,title', 'receiver:id,name', 'creator:id,name']);
        $booking = $payment->booking;
        $summary = $this->summary($booking);

        return [
            'reference' => $payment->reference,
            'amount' => (string) $payment->amount,
            'method' => $payment->method()->label(),
            'paid_at' => $payment->paid_at,
            'external_reference' => $payment->external_reference,
            'note' => $payment->note,
            'received_by' => $payment->receiver?->name ?? $payment->creator?->name,
            'booking' => [
                'reference' => $booking->booking_reference_id,
                'customer_name' => $booking->customer_name,
                'tour' => $booking->package?->title,
                'travel_date' => $booking->travel_date,
            ],
            // Balance math only — never commissions or ledger internals.
            'balance' => [
                'total' => number_format($summary['total'], 2),
                'paid' => number_format($summary['paid'], 2),
                'refunded' => number_format($summary['refunded'], 2),
                'due' => number_format($summary['due'], 2),
                'currency' => $summary['currency'],
            ],
        ];
    }
}
