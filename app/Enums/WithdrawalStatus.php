<?php

namespace App\Enums;

/**
 * Vendor withdrawal request lifecycle (Phase 7, manual settlement).
 *
 * pending → approved → paid is the happy path. Holds are released on
 * rejected/cancelled; on paid the hold converts to a settlement without
 * double-debiting (release + settlement pair). No gateway integration yet.
 */
enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * Statuses this request may move to from the current one.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Paid, self::Rejected, self::Cancelled],
            self::Rejected, self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
