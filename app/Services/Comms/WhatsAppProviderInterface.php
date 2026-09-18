<?php

namespace App\Services\Comms;

/**
 * WhatsApp provider contract. Future adapters (Meta Cloud API, Twilio,
 * ...) implement this seam.
 */
interface WhatsAppProviderInterface
{
    public function key(): string;

    public function isConfigured(): bool;

    /**
     * @param  array<string, mixed>  $meta
     * @return array{provider_reference?: ?string, share_url?: ?string}
     *
     * @throws ProviderNotConfiguredException
     */
    public function send(string $to, string $message, array $meta = []): array;
}
