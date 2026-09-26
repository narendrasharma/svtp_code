<?php

namespace App\AI\Support;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\AIToolCallingProviderInterface;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\AIToolRequest;
use App\AI\DTOs\AIToolResponse;
use App\AI\DTOs\ProviderCapabilities;
use Illuminate\Support\Facades\Log;

class AIManager
{
    public function __construct(
        private readonly AIProviderRegistry $registry,
        private readonly AISettings $settings,
    ) {}

    public function provider(?string $key = null): AIProviderInterface
    {
        if (! $this->settings->enabled()) {
            throw AIException::disabled();
        }

        $key ??= $this->settings->providerKey();
        $provider = $this->registry->get($key);

        if (! $provider || ! $this->settings->hasCredential($key)) {
            throw AIException::unconfigured();
        }

        return $provider;
    }

    public function capabilities(?string $key = null): ProviderCapabilities
    {
        return $this->provider($key)->capabilities();
    }

    public function available(): bool
    {
        try {
            return $this->settings->model($this->provider()->key()) !== '';
        } catch (AIException) {
            return false;
        }
    }

    public function chatWithTools(AIToolRequest $request): AIToolResponse
    {
        $provider = $this->provider();

        if (! $provider instanceof AIToolCallingProviderInterface || ! $provider->capabilities()->supports('tool_calling')) {
            throw AIException::unsupportedTools();
        }

        $model = $this->settings->model($provider->key());

        if ($model === '') {
            throw AIException::unconfigured();
        }

        $startedAt = microtime(true);

        try {
            $response = $provider->chatWithTools($request->withModel($model));
            Log::info('AI generation completed', [
                'provider' => $response->provider,
                'model' => $response->model,
                'use_case' => 'knowledge_agent',
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'success' => true,
                'usage' => $response->usage,
            ]);

            return $response;
        } catch (AIException $exception) {
            $exception = $exception->reason === 'rate_limited' ? AIException::assistantRateLimited($provider->key()) : $exception;

            Log::warning('AI generation failed', [
                'provider' => $provider->key(), 'model' => $model,
                'use_case' => 'knowledge_agent', 'success' => false,
                'reason' => $exception->reason,
            ]);

            throw $exception;
        }
    }

    public function generate(AIRequest $request, string $useCase, ?string $key = null): AIResponse
    {
        $provider = $this->provider($key);
        $model = $request->model ?: $this->settings->model($provider->key());

        if ($model === '') {
            throw AIException::unconfigured();
        }

        $startedAt = microtime(true);

        try {
            $response = $provider->generate($request->withModel($model));
            Log::info('AI generation completed', [
                'provider' => $response->provider,
                'model' => $response->model,
                'use_case' => $useCase,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'success' => true,
                'usage' => $response->usage,
            ]);

            return $response;
        } catch (AIException $exception) {
            Log::warning('AI generation failed', [
                'provider' => $provider->key(),
                'model' => $model,
                'use_case' => $useCase,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'success' => false,
                'reason' => $exception->reason,
            ]);

            throw $exception;
        }
    }
}
