<?php

namespace App\AI\Providers;

use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\Support\AIException;
use App\AI\Support\AISettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiEmbeddingProvider implements EmbeddingProviderInterface
{
    private const DIMENSIONS = 768;

    private const MODELS = ['gemini-embedding-2', 'gemini-embedding-001'];

    public function __construct(private readonly AISettings $settings) {}

    public function key(): string
    {
        return 'gemini';
    }

    public function embed(string $text, string $model, string $purpose = 'document', ?string $title = null): array
    {
        $credential = $this->settings->credential($this->key());

        if (! $credential) {
            throw AIException::unconfigured();
        }

        if (! in_array($model, self::MODELS, true)) {
            throw AIException::unsupportedEmbeddingModel();
        }

        if (! in_array($purpose, ['document', 'query'], true) || trim($text) === '' || mb_strlen($text) > 1200) {
            throw AIException::invalidEmbeddingInput();
        }

        $input = $text;
        $payload = [
            'model' => 'models/'.$model,
            'outputDimensionality' => self::DIMENSIONS,
        ];

        if ($model === 'gemini-embedding-2') {
            $input = $purpose === 'query'
                ? 'task: search result | query: '.$text
                : 'title: '.(trim((string) $title) ?: 'none').' | text: '.$text;
        } else {
            $payload['taskType'] = $purpose === 'query' ? 'RETRIEVAL_QUERY' : 'RETRIEVAL_DOCUMENT';

            if ($purpose === 'document' && filled($title)) {
                $payload['title'] = mb_substr($title, 0, 200);
            }
        }

        $payload['content'] = ['parts' => [['text' => $input]]];

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $credential])
                ->connectTimeout(5)->timeout(20)
                ->retry(2, 750, fn (Throwable $exception, PendingRequest $request): bool => $exception instanceof RequestException
                    && in_array($exception->response->status(), [500, 502, 503, 504], true), throw: false)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':embedContent', $payload);
        } catch (ConnectionException) {
            throw AIException::timeout();
        }

        if ($response->failed()) {
            throw match ($response->status()) {
                400 => AIException::invalidProviderRequest(),
                403 => AIException::providerAccessDenied(),
                404 => AIException::unsupportedEmbeddingModel(),
                default => AIException::fromHttpStatus($response->status()),
            };
        }

        $values = $response->json('embedding.values');

        if (! is_array($values) || ! array_is_list($values) || count($values) !== self::DIMENSIONS) {
            throw AIException::invalidResponse();
        }

        foreach ($values as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw AIException::invalidResponse();
            }
        }

        return array_map('floatval', $values);
    }
}
