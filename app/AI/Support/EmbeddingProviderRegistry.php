<?php

namespace App\AI\Support;

use App\AI\Contracts\EmbeddingProviderInterface;

class EmbeddingProviderRegistry
{
    /** @var array<string, EmbeddingProviderInterface> */
    private array $providers = [];

    public function register(EmbeddingProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): ?EmbeddingProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
