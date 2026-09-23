<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared helpers for marketplace notifications (Phase 9).
 *
 * Delivery is synchronous (no ShouldQueue): no queue worker is guaranteed
 * in production, so queueing must never silently drop transactional mail.
 * Adding ShouldQueue to these classes later is the only change needed once
 * a worker exists. Mail honors the recipient's opt-out preference category;
 * database rows are always stored.
 */
abstract class MarketplaceNotification extends Notification
{
    /**
     * Preference category for mail opt-out (booking|marketplace).
     */
    abstract protected function mailCategory(): string;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'mailPreference') && $notifiable->mailPreference($this->mailCategory())) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    protected function siteName(): string
    {
        return (string) (Setting::getValue('site_name') ?? config('app.name', 'Tour Platform'));
    }

    protected function contactLine(): ?string
    {
        $email = Setting::getValue('contact_email');
        $phone = Setting::getValue('primary_phone');

        return match (true) {
            $email && $phone => "Reach us at {$email} or {$phone}.",
            (bool) $email => "Reach us at {$email}.",
            (bool) $phone => "Reach us at {$phone}.",
            default => null,
        };
    }

    protected function absoluteUrl(string $relativePath): string
    {
        if (preg_match('/^https?:\/\//i', $relativePath) === 1) {
            return $relativePath;
        }

        return rtrim((string) config('app.url'), '/').$relativePath;
    }

    /**
     * @param  array{subject: string, greeting: string, lines: array<int, string>, action_text?: ?string, action_url?: ?string}  $content
     */
    protected function buildMail(array $content): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[{$this->siteName()}] {$content['subject']}")
            ->greeting($content['greeting']);

        foreach ($content['lines'] as $line) {
            $mail->line($line);
        }

        if (! empty($content['action_text']) && ! empty($content['action_url'])) {
            $mail->action($content['action_text'], $this->absoluteUrl($content['action_url']));
        }

        if ($contact = $this->contactLine()) {
            $mail->line($contact);
        }

        return $mail;
    }
}
