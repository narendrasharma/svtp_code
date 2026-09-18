<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a booking is created (any channel: guest, customer, admin).
 * Listeners notify the customer (when linked) and the assigned vendor.
 */
class BookingCreated
{
    use Dispatchable;

    public function __construct(public Booking $booking) {}
}
