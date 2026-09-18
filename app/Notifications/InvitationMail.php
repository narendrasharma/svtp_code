<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Account-claim invitation email. Deliberately outside
 * MarketplaceNotification: this is account-security mail, so it is
 * never gated by marketing/transactional opt-outs.
 */
class InvitationMail extends Notification
{
    public function __construct(
        public string $subject,
        public string $body,
        public string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting('Hello,')
            ->line($this->body)
            ->action('Set your password', $this->url)
            ->line('This link expires in 72 hours and can be used once. If you did not expect this, ignore this email.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'account_invitation',
            'title' => $this->subject,
            'message' => 'An account invitation was emailed to you.',
            'action_url' => null,
            'meta' => [],
        ];
    }
}
