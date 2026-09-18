<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Notifications\AdminAlert;
use App\Notifications\BookingActivity;
use App\Notifications\VendorBookingActivity;

/**
 * Booking created fan-out: customer confirmation + vendor assignment notice.
 * Admin-owned bookings notify nobody on the vendor side (no fake vendor).
 */
class NotifyBookingCreated
{
    use NotifiesAdmins;

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        if ($booking->user) {
            $booking->user->notify(new BookingActivity($booking, 'created'));
        }

        $vendorUser = $booking->vendorProfile?->user;

        if ($vendorUser) {
            $vendorUser->notify(new VendorBookingActivity($booking, 'new_booking'));
        }

        $this->notifyAdmins(new AdminAlert('booking_created', [
            'booking_id' => $booking->id,
            'reference' => $booking->booking_reference_id,
            'total' => number_format((float) $booking->total_amount, 2),
        ]));
    }
}
