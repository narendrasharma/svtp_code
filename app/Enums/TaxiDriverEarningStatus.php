<?php

namespace App\Enums;

/**
 * Driver earning lifecycle (Phase 12A.10).
 *
 * pending → payable → partially_paid → paid is the happy path. paid_at
 * is set only when fully paid. void is terminal for unpaid rows and
 * never rewrites history — adjustments stay on the record.
 */
enum TaxiDriverEarningStatus: string
{
    case Pending = 'pending';
    case Payable = 'payable';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Payable => 'Payable',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function isUnpaid(): bool
    {
        return in_array($this, [self::Pending, self::Payable, self::PartiallyPaid], true);
    }
}
