<?php

namespace App\Listeners;

use App\Events\CancellationReviewed;
use App\Notifications\BookingActivity;

/**
 * Cancellation decision notice to the booking owner.
 */
class NotifyCancellationReviewed
{
    public function handle(CancellationReviewed $event): void
    {
        $booking = $event->cancellation->booking;

        if ($booking->user) {
            $booking->user->notify(new BookingActivity(
                $booking,
                $event->approved ? 'cancellation_approved' : 'cancellation_rejected'
            ));
        }
    }
}
