<?php

namespace App\Enums;

/**
 * Support ticket lifecycle. Reopening moves resolved/closed back to
 * open — no separate "reopened" state is stored.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case PendingStaff = 'pending_staff';
    case PendingCustomer = 'pending_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::PendingStaff => 'Pending (staff)',
            self::PendingCustomer => 'Pending (requester)',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
