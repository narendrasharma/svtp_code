<?php

namespace App\Notifications;

use App\Models\TaxiBooking;
use Illuminate\Notifications\Messages\MailMessage;

class TaxiTrackingLinkNotification extends MarketplaceNotification
{
    public function __construct(
        public TaxiBooking $booking,
        public string $trackingUrl,
        public bool $guestRecipient = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->guestRecipient ? ['mail'] : parent::via($notifiable);
    }

    protected function mailCategory(): string
    {
        return 'booking';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->buildMail([
            'subject' => __('common.taxi_tracking_subject'),
            'greeting' => 'Hello,',
            'lines' => [__('common.taxi_tracking_available', ['reference' => $this->booking->reference]), __('common.private_tracking_link')],
            'action_text' => __('common.open_tracking'),
            'action_url' => $this->trackingUrl,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'taxi_tracking_available',
            'title' => __('common.taxi_tracking_subject'),
            'message' => __('common.taxi_tracking_available', ['reference' => $this->booking->reference]),
            'action_url' => "/account/taxi/changes/{$this->booking->id}",
            'reference' => $this->booking->reference,
        ];
    }
}
