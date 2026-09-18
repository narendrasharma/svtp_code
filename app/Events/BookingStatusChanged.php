<?php

namespace App\Events;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a booking lifecycle transition (not payment-only changes).
 * Completion also drives the customer review invitation.
 */
class BookingStatusChanged
{
    use Dispatchable;

    public function __construct(
        public Booking $booking,
        public BookingStatus $from,
        public BookingStatus $to,
        public ?User $actor = null,
    ) {}
}
