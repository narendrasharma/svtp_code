<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ProviderCapabilities;
use App\AI\Support\AIException;
use App\AI\Support\AISettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ClaudeProvider implements AIProviderInterface
{
    public function __construct(private readonly AISettings $settings) {}

    public function key(): string
    {
        return 'claude';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(['text_generation', 'chat']);
    }

    public function generate(AIRequest $request): AIResponse
    {
        $credential = $this->settings->credential($this->key());

        if (! $credential || ! $request->model) {
            throw AIException::unconfigured();
        }

        $payload = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens ?? 400,
            'messages' => [['role' => 'user', 'content' => $request->userContent]],
        ];

        if ($request->systemInstructions) {
            $payload['system'] = $request->systemInstructions;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $credential,
                'anthropic-version' => '2023-06-01',
            ])->connectTimeout(5)
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (ConnectionException) {
            throw AIException::timeout();
        }

        if ($response->failed()) {
            throw AIException::fromHttpStatus($response->status());
        }

        $text = $response->json('content.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw AIException::invalidResponse();
        }

        $reportedModel = $response->json('model');
        $finishStatus = $response->json('stop_reason');
        $requestId = $response->json('id');
        $usage = $response->json('usage');

        return new AIResponse(
            text: $text,
            provider: $this->key(),
            model: is_string($reportedModel) && $reportedModel !== '' ? $reportedModel : $request->model,
            usage: is_array($usage) ? array_filter([
                'input_tokens' => $usage['input_tokens'] ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
            ], 'is_int') : null,
            finishStatus: is_string($finishStatus) ? $finishStatus : null,
            requestId: is_string($requestId) ? $requestId : null,
        );
    }
}
