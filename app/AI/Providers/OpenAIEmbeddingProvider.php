<?php

namespace App\AI\Providers;

use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\Support\AIException;
use App\AI\Support\AISettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private readonly AISettings $settings) {}

    public function key(): string
    {
        return 'openai';
    }

    public function embed(string $text, string $model, string $purpose = 'document', ?string $title = null): array
    {
        $credential = $this->settings->credential('openai');

        if (! $credential || trim($text) === '') {
            throw AIException::unconfigured();
        }

        try {
            $response = Http::withToken($credential)->connectTimeout(5)->timeout(20)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $model,
                    'input' => $text,
                    'encoding_format' => 'float',
                    'dimensions' => 256,
                ]);
        } catch (ConnectionException) {
            throw AIException::timeout();
        }

        if ($response->failed()) {
            throw AIException::fromHttpStatus($response->status());
        }

        $vector = $response->json('data.0.embedding');

        if (! is_array($vector) || count($vector) !== 256 || ! array_is_list($vector)) {
            throw AIException::invalidResponse();
        }

        foreach ($vector as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw AIException::invalidResponse();
            }
        }

        return array_map('floatval', $vector);
    }
}
