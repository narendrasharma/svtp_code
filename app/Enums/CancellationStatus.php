<?php

namespace App\Enums;

/**
 * Customer cancellation request states. No money moves here — approval only
 * transitions the booking; refunds arrive with the payment phase.
 */
enum CancellationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
