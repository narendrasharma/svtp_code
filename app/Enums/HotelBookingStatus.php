<?php

namespace App\Enums;

enum HotelBookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending', self::Confirmed => 'Confirmed', self::CheckedIn => 'Checked in',
            self::CheckedOut => 'Checked out', self::Completed => 'Completed', self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }
}
