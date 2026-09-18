<?php

namespace App\Enums;

/**
 * Immutable vendor ledger entry types (Phase 7).
 *
 * The ledger belongs to the vendor — never to a single product. Booking
 * references are optional context so future Taxi/Hotel earnings credit the
 * same ledger. Booking snapshots stay immutable; every later money movement
 * is an append-only entry here.
 */
enum LedgerEntryType: string
{
    case BookingEarning = 'booking_earning';
    case RefundReversal = 'refund_reversal';
    case AdjustmentCredit = 'adjustment_credit';
    case AdjustmentDebit = 'adjustment_debit';
    case WithdrawalHold = 'withdrawal_hold';
    case WithdrawalRelease = 'withdrawal_release';
    case PayoutSettlement = 'payout_settlement';

    public function label(): string
    {
        return match ($this) {
            self::BookingEarning => 'Booking earning',
            self::RefundReversal => 'Refund reversal',
            self::AdjustmentCredit => 'Adjustment credit',
            self::AdjustmentDebit => 'Adjustment debit',
            self::WithdrawalHold => 'Withdrawal hold',
            self::WithdrawalRelease => 'Withdrawal release',
            self::PayoutSettlement => 'Payout settlement',
        };
    }

    public function direction(): LedgerDirection
    {
        return match ($this) {
            self::BookingEarning,
            self::AdjustmentCredit,
            self::WithdrawalRelease => LedgerDirection::Credit,
            self::RefundReversal,
            self::AdjustmentDebit,
            self::WithdrawalHold,
            self::PayoutSettlement => LedgerDirection::Debit,
        };
    }
}
