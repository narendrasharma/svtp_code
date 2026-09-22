<?php

namespace App\Enums;

enum HotelReservationStatus: string
{
    case Held = 'held';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
