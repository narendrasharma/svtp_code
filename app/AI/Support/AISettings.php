<?php

namespace App\AI\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class AISettings
{
    public function enabled(): bool
    {
        $stored = Setting::getValue('ai.enabled');

        if ($stored !== null) {
            return $stored === '1';
        }

        $configured = config('services.ai.enabled');

        return $configured === null ? $this->hasCredential($this->providerKey()) : filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }

    public function providerKey(): string
    {
        return (string) (Setting::getValue('ai.provider') ?: config('services.ai.provider', 'openai'));
    }

    public function knowledgeEnabled(): bool
    {
        $stored = Setting::getValue('ai.knowledge.enabled');

        return $stored === null ? (bool) config('services.ai.knowledge.enabled', false) : $stored === '1';
    }

    public function agentActionsEnabled(): bool
    {
        $stored = Setting::getValue('ai.agent_actions.enabled');

        return $stored === null ? (bool) config('services.ai.actions.enabled', false) : $stored === '1';
    }

    public function embeddingProviderKey(): string
    {
        return (string) (Setting::getValue('ai.embedding.provider') ?: config('services.ai.embedding.provider', 'openai'));
    }

    public function embeddingModel(): string
    {
        return (string) (Setting::getValue('ai.embedding.model') ?: config('services.ai.embedding.model', 'text-embedding-3-small'));
    }

    public function model(string $provider): string
    {
        $configuredModel = $provider === $this->providerKey() ? Setting::getValue('ai.model') : null;

        return (string) ($configuredModel ?: config("services.ai.providers.{$provider}.model", ''));
    }

    public function azureEndpoint(): string
    {
        return trim((string) (Setting::getValue('ai.azure.endpoint') ?: config('services.ai.providers.azure.endpoint', '')));
    }

    public function azureToolCallingEnabled(): bool
    {
        $stored = Setting::getValue('ai.azure.tool_calling');

        return $stored === null ? (bool) config('services.ai.providers.azure.tool_calling', false) : $stored === '1';
    }

    public function credential(string $provider): ?string
    {
        $encrypted = Setting::getValue("ai.{$provider}.key");

        if ($encrypted !== null && $encrypted !== '') {
            try {
                return Crypt::decryptString($encrypted);
            } catch (DecryptException) {
                throw AIException::unconfigured();
            }
        }

        return config("services.ai.providers.{$provider}.key") ?: null;
    }

    public function hasCredential(string $provider): bool
    {
        return filled(Setting::getValue("ai.{$provider}.key")) || filled(config("services.ai.providers.{$provider}.key"));
    }

    public function saveCredential(string $provider, string $credential): void
    {
        Setting::setValue("ai.{$provider}.key", Crypt::encryptString($credential));
    }
}
