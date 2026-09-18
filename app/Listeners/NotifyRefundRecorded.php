<?php

namespace App\Listeners;

use App\Events\RefundRecorded;
use App\Notifications\BookingActivity;
use App\Notifications\VendorBookingActivity;

/**
 * Refund fan-out: customer (amount) + assigned vendor (earning impact).
 */
class NotifyRefundRecorded
{
    public function handle(RefundRecorded $event): void
    {
        $refund = $event->refund->loadMissing('booking.package', 'booking.user', 'booking.vendorProfile.user');
        $booking = $refund->booking;

        if ($booking->user) {
            $booking->user->notify(new BookingActivity($booking, 'refunded', ['amount' => (string) $refund->amount]));
        }

        if ($vendorUser = $booking->vendorProfile?->user) {
            $vendorUser->notify(new VendorBookingActivity($booking, 'refunded', ['amount' => (string) $refund->amount]));
        }
    }
}
