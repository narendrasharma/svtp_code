<?php

namespace App\Services\Comms;

/**
 * Default SMS provider: none configured. send() always throws so the
 * app can never silently pretend an SMS went out. The UI disables SMS
 * options while this is the active provider.
 */
class NullSmsProvider implements SmsProviderInterface
{
    public function key(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function send(string $to, string $message, array $meta = []): array
    {
        throw new ProviderNotConfiguredException('No SMS provider is configured. Set SMS_PROVIDER to enable SMS sending.');
    }
}
