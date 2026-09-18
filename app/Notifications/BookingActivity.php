<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Customer booking lifecycle notices (database + mail).
 *
 * Kinds: created, confirmed, cancelled, cancellation_approved,
 * cancellation_rejected, refunded, completed, review_invitation.
 * Payloads carry display strings and ids only — never secrets.
 */
class BookingActivity extends MarketplaceNotification
{
    public function __construct(
        public Booking $booking,
        public string $kind,
        public array $extra = [],
    ) {}

    protected function mailCategory(): string
    {
        return 'booking';
    }

    public function title(): string
    {
        $ref = $this->booking->booking_reference_id;

        return match ($this->kind) {
            'created' => "Booking {$ref} received",
            'confirmed' => "Booking {$ref} confirmed",
            'cancelled' => "Booking {$ref} cancelled",
            'cancellation_approved' => "Cancellation approved for {$ref}",
            'cancellation_rejected' => "Cancellation update for {$ref}",
            'refunded' => "Refund recorded for {$ref}",
            'completed' => "How was your tour? {$ref}",
            'review_invitation' => "Share your experience: {$ref}",
            default => "Booking {$ref} update",
        };
    }

    public function message(): string
    {
        $tour = $this->booking->package?->title ?? 'your tour';

        return match ($this->kind) {
            'created' => "We received your booking for {$tour}. We will confirm shortly.",
            'confirmed' => "Your booking for {$tour} is confirmed. See you on {$this->booking->travel_date?->toDateString()}.",
            'cancelled' => "Your booking for {$tour} has been cancelled.",
            'cancellation_approved' => 'Your cancellation request was approved.',
            'cancellation_rejected' => 'Your cancellation request was reviewed. Please contact us for assistance.',
            'refunded' => 'A refund of ₹'.($this->extra['amount'] ?? $this->booking->total_amount).' was recorded for your booking.',
            'completed' => "Your tour {$tour} is marked completed. We would love your review.",
            'review_invitation' => "Tell fellow travellers about {$tour} — it takes a minute.",
            default => 'There is an update on your booking.',
        };
    }

    public function actionUrl(): string
    {
        return "/account/bookings/{$this->booking->id}";
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
        $name = $notifiable->name ?? 'Traveller';

        return $this->buildMail([
            'subject' => $this->title(),
            'greeting' => "Hello {$name},",
            'lines' => [$this->message()],
            'action_text' => 'View booking',
            'action_url' => $this->actionUrl(),
        ]);
    }
}
