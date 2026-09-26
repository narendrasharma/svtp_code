<?php

namespace App\AI\Support;

use App\AI\Contracts\AIProviderInterface;

class AIProviderRegistry
{
    /** @var array<string, AIProviderInterface> */
    private array $providers = [];

    public function register(AIProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): ?AIProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
