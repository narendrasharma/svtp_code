<?php

namespace App\Enums;

/**
 * Controlled auto-dispatch offer lifecycle (12A.8).
 *
 * Offers are historical records — statuses only move forward, rows are
 * never rewritten except pending → terminal. Terminal offers are final.
 */
enum TaxiDispatchOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
