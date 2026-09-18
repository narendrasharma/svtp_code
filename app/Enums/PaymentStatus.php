<?php

namespace App\Enums;

/**
 * Payment states for a booking. Kept separate from the booking lifecycle so
 * future gateways, partial payments and refunds plug in without touching it.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        };
    }
}
