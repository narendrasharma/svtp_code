<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Database-only delivery is atomic with the review write and needs no worker. */
class HotelReviewNotification extends Notification
{
    public function __construct(
        public string $kind,
        public string $propertyName,
        public string $actionUrl,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'hotel_review_'.$this->kind,
            'title' => match ($this->kind) {
                'pending' => 'Hotel review awaiting moderation',
                'approved' => 'Your hotel review is published',
                'rejected' => 'Your hotel review was not approved',
                'received' => 'New verified stay review',
                'replied' => 'The property responded to your review',
            },
            'message' => 'Review for '.$this->propertyName.'. Open the review for details.',
            'action_url' => $this->actionUrl,
        ];
    }
}
