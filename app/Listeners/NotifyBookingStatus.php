<?php

namespace App\Listeners;

use App\Enums\BookingStatus;
use App\Events\BookingStatusChanged;
use App\Notifications\BookingActivity;
use App\Notifications\VendorBookingActivity;

/**
 * Booking status fan-out. Completion sends the status notice plus a review
 * invitation (the invitation is the actionable CTA). Cancellations also
 * reach the assigned vendor.
 */
class NotifyBookingStatus
{
    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking;

        $customerKind = match ($event->to) {
            BookingStatus::Confirmed => 'confirmed',
            BookingStatus::Cancelled => 'cancelled',
            BookingStatus::Completed => 'completed',
            default => null,
        };

        if ($customerKind && $booking->user) {
            $booking->user->notify(new BookingActivity($booking, $customerKind));
        }

        if ($event->to === BookingStatus::Completed && $booking->user) {
            $booking->user->notify(new BookingActivity($booking, 'review_invitation'));
        }

        if ($event->to === BookingStatus::Cancelled && ($vendorUser = $booking->vendorProfile?->user)) {
            $vendorUser->notify(new VendorBookingActivity($booking, 'cancelled'));
        }
    }
}
