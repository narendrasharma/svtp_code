<?php

namespace App\Enums;

/**
 * Driver payout batch lifecycle (Phase 12A.10).
 *
 * draft → processing → paid is the happy path. draft/processing may be
 * cancelled (allocations released, earnings stay payable). paid is
 * terminal — corrections use earning adjustments, never silent edits.
 * failed is reserved for a future gateway integration.
 */
enum TaxiDriverPayoutStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

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
            self::Draft => 'Draft',
            self::Processing => 'Processing',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Processing, self::Cancelled],
            self::Processing => [self::Paid, self::Cancelled],
            self::Paid, self::Cancelled, self::Failed => [],
        };
    }
}
