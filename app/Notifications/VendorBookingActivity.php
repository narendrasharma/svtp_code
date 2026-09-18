<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Vendor booking notices (database + mail).
 *
 * Kinds: new_booking, cancelled, refunded. Links resolve through normal
 * BookingPolicy checks — the link itself grants nothing.
 */
class VendorBookingActivity extends MarketplaceNotification
{
    public function __construct(
        public Booking $booking,
        public string $kind,
        public array $extra = [],
    ) {}

    protected function mailCategory(): string
    {
        return 'marketplace';
    }

    public function title(): string
    {
        $ref = $this->booking->booking_reference_id;

        return match ($this->kind) {
            'new_booking' => "New booking assigned: {$ref}",
            'cancelled' => "Booking cancelled: {$ref}",
            'refunded' => "Refund affects booking {$ref}",
            default => "Booking update: {$ref}",
        };
    }

    public function message(): string
    {
        $tour = $this->booking->package?->title ?? 'your tour';

        return match ($this->kind) {
            'new_booking' => "A new booking for {$tour} ({$this->booking->guestCount()} guest(s), travel {$this->booking->travel_date?->toDateString()}) was assigned to your business.",
            'cancelled' => "Booking {$this->booking->booking_reference_id} for {$tour} was cancelled.",
            'refunded' => 'A refund of ₹'.($this->extra['amount'] ?? '?')." was recorded; your earning of ₹{$this->booking->vendor_earning_amount} is reversed proportionally.",
            default => 'There is an update on an assigned booking.',
        };
    }

    public function actionUrl(): string
    {
        return "/vendor/bookings/{$this->booking->id}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title(),
            'message' => $this->message(),
            'action_url' => $this->actionUrl(),
            'booking_id' => $this->booking->id,
            'booking_reference_id' => $this->booking->booking_reference_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->buildMail([
            'subject' => $this->title(),
            'greeting' => 'Hello,',
            'lines' => [$this->message()],
            'action_text' => 'View booking',
            'action_url' => $this->actionUrl(),
        ]);
    }
}
