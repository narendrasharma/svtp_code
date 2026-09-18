<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\RefundRecorded;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manual accounting refunds with proportional vendor reversals (Phase 8).
 *
 * No gateway integration: a processed refund is an accounting record, and
 * the admin UI states that explicitly. Each processed refund owns exactly
 * one vendor reversal ledger entry; booking snapshots stay immutable.
 *
 * Vendor share uses the ORIGINAL snapshot ratio (earning / gross), never the
 * current commission setting. Cumulative reversals reconcile exactly: every
 * refund reverses (round_half_up(total_refunded × earning / gross) − prior),
 * so the final refund lands precisely on the original earning with no
 * 0.01 drift. All math is integer paise.
 *
 * Legacy compatibility: Phase 7 full reversals (ledger-only, reference
 * booking-reversal:{id}) count as fully reversed. Once partial refunds
 * exist for a booking, the legacy markPayment(refunded) path stands down
 * (guarded in VendorLedgerService) and this service owns all reversals.
 */
class BookingRefundService
{
    public function __construct(
        protected VendorLedgerService $ledger,
        protected BookingService $bookings,
    ) {}

    /**
     * Customer-facing refundable remainder (gross − processed refunds).
     */
    public function refundableRemaining(Booking $booking): string
    {
        $refunded = $this->processedRefundTotal($booking);

        return VendorLedgerService::fromPaise(
            VendorLedgerService::toPaise((string) $booking->gross_amount) - VendorLedgerService::toPaise($refunded)
        );
    }

    public function processedRefundTotal(Booking $booking): string
    {
        $total = 0;

        BookingRefund::where('booking_id', $booking->id)
            ->where('status', RefundStatus::Processed->value)
            ->orderBy('id')
            ->chunkById(200, function ($refunds) use (&$total): void {
                foreach ($refunds as $refund) {
                    $total += VendorLedgerService::toPaise((string) $refund->getRawOriginal('amount'));
                }
            });

        return VendorLedgerService::fromPaise($total);
    }

    /**
     * Vendor earning already reversed (legacy full reversal or partials).
     */
    public function reversedVendorTotal(Booking $booking): string
    {
        if (VendorLedgerEntry::where('reference', VendorLedgerService::earningReference($booking->id))->doesntExist()) {
            return '0.00';
        }

        if (VendorLedgerEntry::where('reference', VendorLedgerService::reversalReference($booking->id))->exists()) {
            return VendorLedgerService::toDecimal((string) $booking->vendor_earning_amount);
        }

        $total = 0;
        VendorLedgerEntry::where('booking_id', $booking->id)
            ->where('type', LedgerEntryType::RefundReversal->value)
            ->whereNotNull('booking_refund_id')
            ->orderBy('id')
            ->chunkById(200, function ($entries) use (&$total): void {
                foreach ($entries as $entry) {
                    $total += VendorLedgerService::toPaise((string) $entry->getRawOriginal('amount'));
                }
            });

        return VendorLedgerService::fromPaise($total);
    }

    public function remainingVendorEarning(Booking $booking): string
    {
        return VendorLedgerService::fromPaise(
            VendorLedgerService::toPaise((string) $booking->vendor_earning_amount)
            - VendorLedgerService::toPaise($this->reversedVendorTotal($booking))
        );
    }

    /**
     * Proportional vendor reversal for a new refund, reconciled against the
     * cumulative total so the final refund lands exactly on the snapshot.
     */
    public function proportionalVendorReversal(Booking $booking, float|string $refundAmount): string
    {
        $grossPaise = VendorLedgerService::toPaise((string) $booking->gross_amount);
        $earningPaise = VendorLedgerService::toPaise((string) $booking->vendor_earning_amount);

        if ($grossPaise <= 0 || $earningPaise <= 0) {
            return '0.00';
        }

        $newRefundedPaise = VendorLedgerService::toPaise($this->processedRefundTotal($booking))
            + VendorLedgerService::toPaise($refundAmount);

        // Half-up: floor((2ab + c) / 2c), exact in integers.
        $expectedCumulative = intdiv($newRefundedPaise * $earningPaise * 2 + $grossPaise, 2 * $grossPaise);
        $prior = VendorLedgerService::toPaise($this->reversedVendorTotal($booking));

        $reversal = max(0, min($expectedCumulative - $prior, $earningPaise - $prior));

        return VendorLedgerService::fromPaise($reversal);
    }

    /**
     * Record a processed manual refund + its vendor reversal atomically.
     *
     * Pass $reference (client-generated per form render) for retry
     * idempotency: a repeated submission resolves to the original row.
     */
    public function recordRefund(
        Booking $booking,
        float|string $amount,
        string $reason,
        User $admin,
        ?string $externalReference = null,
        ?string $reference = null,
    ): BookingRefund {
        if ($booking->vendor_profile_id === null) {
            throw ValidationException::withMessages(['booking' => 'Only vendor bookings carry refundable vendor economics.']);
        }

        if ($booking->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages(['booking' => 'Only paid bookings can be refunded.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A refund reason is required.']);
        }

        if ($reference) {
            $existing = BookingRefund::where('reference', $reference)->first();

            if ($existing) {
                return $existing;
            }
        }

        $amountDecimal = VendorLedgerService::toDecimal($amount);
        $amountPaise = VendorLedgerService::toPaise($amountDecimal);
        $remainingPaise = VendorLedgerService::toPaise($this->refundableRemaining($booking));

        if ($amountPaise <= 0) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
        }

        if ($amountPaise > $remainingPaise) {
            throw ValidationException::withMessages([
                'amount' => 'Refund exceeds the refundable remainder of ₹'.VendorLedgerService::fromPaise($remainingPaise).'.',
            ]);
        }

        $refund = DB::transaction(function () use ($booking, $amountDecimal, $reason, $admin, $externalReference, $reference): BookingRefund {
            if ($reference && ($existing = BookingRefund::where('reference', $reference)->first())) {
                return $existing;
            }

            $reversal = $this->proportionalVendorReversal($booking, $amountDecimal);

            $refund = BookingRefund::create([
                'booking_id' => $booking->id,
                'amount' => $amountDecimal,
                'currency' => $booking->currency ?? 'INR',
                'status' => RefundStatus::Processed->value,
                'reason' => $reason,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'reference' => $reference ?? 'booking-refund:'.Str::uuid()->toString(),
                'external_reference' => $externalReference,
                'vendor_reversal_amount' => $reversal,
            ]);

            if (VendorLedgerService::toPaise($reversal) > 0) {
                VendorLedgerEntry::create([
                    'vendor_profile_id' => (int) $booking->vendor_profile_id,
                    'booking_id' => $booking->id,
                    'booking_refund_id' => $refund->id,
                    'withdrawal_request_id' => null,
                    'type' => LedgerEntryType::RefundReversal->value,
                    'direction' => LedgerDirection::Debit->value,
                    'amount' => $reversal,
                    'currency' => $refund->currency,
                    'reference' => "refund-reversal:{$refund->id}",
                    'description' => "Refund reversal {$booking->booking_reference_id} (₹{$amountDecimal})",
                    'metadata' => [
                        'booking_reference_id' => $booking->booking_reference_id,
                        'refund_id' => $refund->id,
                        'refund_amount' => $amountDecimal,
                    ],
                    'created_by' => $admin->id,
                ]);
            }

            // Fully refunded ⇒ payment reflects reality. The legacy
            // markPayment(refunded) reversal stands down because partials
            // exist (guarded), so no double reversal is possible.
            if (VendorLedgerService::toPaise($this->refundableRemaining($booking->refresh())) === 0) {
                $this->bookings->markPayment($booking->refresh(), PaymentStatus::Refunded, $admin, 'Fully refunded via recorded refunds');
            }

            return $refund;
        });

        // Retried submissions resolve to the stored row without re-notifying.
        if ($refund->wasRecentlyCreated) {
            RefundRecorded::dispatch($refund);
        }

        return $refund;
    }
}
