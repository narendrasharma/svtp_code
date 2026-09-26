<?php

namespace App\AI\Support;

use App\AI\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Log;

class EmbeddingManager
{
    public function __construct(private readonly AISettings $settings, private readonly EmbeddingProviderRegistry $registry) {}

    public function provider(): EmbeddingProviderInterface
    {
        if (! $this->settings->enabled() || ! $this->settings->knowledgeEnabled()) {
            throw AIException::disabled();
        }

        $key = $this->settings->embeddingProviderKey();
        $provider = $this->registry->get($key);

        if (! $provider || ! $this->settings->hasCredential($key)) {
            throw AIException::unconfigured();
        }

        return $provider;
    }

    public function model(): string
    {
        return $this->settings->embeddingModel();
    }

    /** @return list<float> */
    public function embed(string $text, string $purpose = 'document', ?string $title = null): array
    {
        $provider = $this->provider();
        $startedAt = microtime(true);

        try {
            $vector = $provider->embed($text, $this->model(), $purpose, $title);
            Log::info('AI embedding completed', [
                'use_case' => 'knowledge_embedding', 'provider' => $provider->key(), 'model' => $this->model(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000), 'success' => true,
            ]);

            return $vector;
        } catch (AIException $exception) {
            Log::warning('AI embedding failed', [
                'use_case' => 'knowledge_embedding', 'provider' => $provider->key(), 'model' => $this->model(),
                'success' => false, 'reason' => $exception->reason,
            ]);

            throw $exception;
        }
    }
}
