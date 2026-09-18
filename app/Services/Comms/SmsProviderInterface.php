<?php

namespace App\Services\Comms;

/**
 * Generic SMS provider contract. Future adapters (MSG91, Twilio, ...)
 * implement this — the rest of the app never touches vendor SDKs.
 */
interface SmsProviderInterface
{
    public function key(): string;

    public function isConfigured(): bool;

    /**
     * @param  array<string, mixed>  $meta
     * @return array{provider_reference?: ?string}
     *
     * @throws ProviderNotConfiguredException
     */
    public function send(string $to, string $message, array $meta = []): array;
}
