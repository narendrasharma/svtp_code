<?php

namespace App\Services\Comms;

/**
 * Default WhatsApp mode: manual share links. No API credentials, no
 * delivery claims — send() returns a wa.me share URL the staffer opens.
 * A real provider later replaces this class behind the same interface.
 */
class ManualWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(protected ShareService $share) {}

    public function key(): string
    {
        return 'manual';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $to, string $message, array $meta = []): array
    {
        $url = $this->share->whatsappShareUrl($to, $message);

        if ($url === null) {
            throw new ProviderNotConfiguredException('No usable phone number for WhatsApp share.');
        }

        return ['share_url' => $url];
    }
}
