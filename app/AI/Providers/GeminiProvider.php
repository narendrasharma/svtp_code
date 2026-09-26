<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIToolCallingProviderInterface;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\AIToolCall;
use App\AI\DTOs\AIToolRequest;
use App\AI\DTOs\AIToolResponse;
use App\AI\DTOs\ProviderCapabilities;
use App\AI\Support\AIException;
use App\AI\Support\AISettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiProvider implements AIToolCallingProviderInterface
{
    private const AGENT_MODELS = [
        'gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.6-flash',
        'gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite',
        'gemini-3.1-pro-preview', 'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite',
    ];

    public function __construct(private readonly AISettings $settings) {}

    public function key(): string
    {
        return 'gemini';
    }

    public function capabilities(): ProviderCapabilities
    {
        $capabilities = ['text_generation', 'chat'];

        if ($this->supportsAgentModel($this->settings->model($this->key()))) {
            $capabilities[] = 'tool_calling';
        }

        return new ProviderCapabilities($capabilities);
    }

    public function generate(AIRequest $request): AIResponse
    {
        $credential = $this->settings->credential($this->key());

        if (! $credential || ! $request->model) {
            throw AIException::unconfigured();
        }

        $payload = ['contents' => [['parts' => [['text' => $request->userContent]]]]];

        if ($request->systemInstructions) {
            $payload['systemInstruction'] = ['parts' => [['text' => $request->systemInstructions]]];
        }

        if ($request->temperature !== null && ! str_starts_with($request->model, 'gemini-3.')) {
            $payload['generationConfig']['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $payload['generationConfig']['maxOutputTokens'] = str_starts_with($request->model, 'gemini-3.')
                ? max(1024, $request->maxTokens + 512) : $request->maxTokens;
        }

        if (str_starts_with($request->model, 'gemini-3.')) {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingLevel' => 'low'];
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $credential])
                ->connectTimeout(5)
                ->timeout(30)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($request->model).':generateContent', $payload);
        } catch (ConnectionException) {
            throw AIException::timeout();
        }

        if ($response->failed()) {
            throw AIException::fromHttpStatus($response->status());
        }

        $text = collect($response->json('candidates.0.content.parts') ?? [])
            ->filter(fn (mixed $part): bool => is_array($part) && is_string($part['text'] ?? null) && ! ($part['thought'] ?? false))
            ->pluck('text')->implode("\n");

        if (! is_string($text) || trim($text) === '') {
            throw AIException::invalidResponse();
        }

        $reportedModel = $response->json('modelVersion');
        $finishStatus = $response->json('candidates.0.finishReason');
        $usage = $response->json('usageMetadata');

        return new AIResponse(
            text: $text,
            provider: $this->key(),
            model: is_string($reportedModel) && $reportedModel !== '' ? $reportedModel : $request->model,
            usage: is_array($usage) ? array_filter([
                'input_tokens' => $usage['promptTokenCount'] ?? null,
                'output_tokens' => $usage['candidatesTokenCount'] ?? null,
                'total_tokens' => $usage['totalTokenCount'] ?? null,
            ], 'is_int') : null,
            finishStatus: is_string($finishStatus) ? $finishStatus : null,
            requestId: $response->header('x-request-id'),
        );
    }

    public function chatWithTools(AIToolRequest $request): AIToolResponse
    {
        $credential = $this->settings->credential($this->key());

        if (! $credential || ! $request->model) {
            throw AIException::unconfigured();
        }

        if (! $this->supportsAgentModel($request->model)) {
            throw AIException::unsupportedTools();
        }

        [$system, $contents] = $this->toolContents($request);
        $declarations = array_map(fn (array $tool): array => [
            'name' => $tool['name'], 'description' => $tool['description'],
            'parameters' => $this->geminiSchema($tool['input_schema']),
        ], $request->tools);
        $payload = [
            'contents' => $contents,
            'tools' => [['functionDeclarations' => $declarations]],
            'toolConfig' => ['functionCallingConfig' => ['mode' => $request->requireTool ? 'ANY' : 'AUTO']],
            'generationConfig' => ['maxOutputTokens' => 4096],
        ];

        if ($system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        if (str_starts_with($request->model, 'gemini-3.')) {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingLevel' => 'low'];
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $credential])
                ->connectTimeout(5)->timeout(15)
                ->retry(2, 1000, fn (Throwable $exception, PendingRequest $pending): bool => $exception instanceof RequestException
                    && $exception->response->status() === 503, throw: false)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($request->model).':generateContent', $payload);
        } catch (ConnectionException) {
            Log::warning('Gemini assistant request failed', [
                'stage' => $request->requireTool ? 'initial_tool_declaration' : 'tool_result_roundtrip',
                'model' => $request->model,
                'api_route' => 'v1beta/models:generateContent',
                'reason' => 'connection_timeout',
            ]);

            throw AIException::timeout();
        }

        if ($response->failed()) {
            $this->logToolRequestFailure($response, $request);

            throw match ($response->status()) {
                400 => AIException::invalidProviderRequest(),
                403 => AIException::providerAccessDenied(),
                404 => AIException::unsupportedTools(),
                default => AIException::fromHttpStatus($response->status()),
            };
        }

        $parts = $response->json('candidates.0.content.parts');

        if (! is_array($parts) || ! array_is_list($parts) || $parts === []) {
            throw AIException::invalidResponse();
        }

        $calls = [];
        $seenCallIds = [];
        $text = [];
        $firstCall = true;

        foreach ($parts as $index => $part) {
            if (! is_array($part)) {
                throw AIException::invalidResponse();
            }

            if (isset($part['functionCall'])) {
                $call = $part['functionCall'];

                if ($firstCall && str_starts_with($request->model, 'gemini-3.')
                    && ! is_string($part['thoughtSignature'] ?? null)) {
                    throw AIException::invalidResponse();
                }

                $firstCall = false;

                if (! is_array($call) || ! is_string($call['name'] ?? null) || $call['name'] === ''
                    || ! is_array($call['args'] ?? []) || (isset($call['args']) && array_is_list($call['args']) && $call['args'] !== [])) {
                    throw AIException::invalidResponse();
                }

                $id = $call['id'] ?? null;

                if ((! is_string($id) || $id === '') && str_starts_with($request->model, 'gemini-3.')) {
                    throw AIException::invalidResponse();
                }

                $normalizedId = is_string($id) && $id !== '' ? $id : 'gemini-local-'.$index;

                if (isset($seenCallIds[$normalizedId])) {
                    throw AIException::invalidResponse();
                }

                $seenCallIds[$normalizedId] = true;
                $calls[] = new AIToolCall($normalizedId, $call['name'], $call['args'] ?? []);
            } elseif (is_string($part['text'] ?? null) && ! ($part['thought'] ?? false)) {
                $text[] = $part['text'];
            }
        }

        $answer = trim(implode("\n", $text));

        if ($calls === [] && $answer === '') {
            throw AIException::invalidResponse();
        }

        $state = null;

        if ($calls !== []) {
            $encoded = json_encode($parts, JSON_INVALID_UTF8_SUBSTITUTE);

            if (! is_string($encoded) || strlen($encoded) > 131072) {
                throw AIException::invalidResponse();
            }

            $state = base64_encode($encoded);
        }

        $usage = $response->json('usageMetadata');

        return new AIToolResponse(
            $answer, $calls, $this->key(),
            (string) ($response->json('modelVersion') ?: $request->model),
            is_array($usage) ? array_filter([
                'input_tokens' => $usage['promptTokenCount'] ?? null,
                'output_tokens' => $usage['candidatesTokenCount'] ?? null,
                'total_tokens' => $usage['totalTokenCount'] ?? null,
            ], 'is_int') : null,
            providerState: $state,
        );
    }

    /** @return array{?string, list<array<string, mixed>>} */
    private function toolContents(AIToolRequest $request): array
    {
        $system = null;
        $contents = [];
        $callNames = [];
        $pendingResults = [];

        foreach ($request->messages as $message) {
            if ($message->role === 'system') {
                $system = $message->content;

                continue;
            }

            if ($message->role === 'tool') {
                $call = $callNames[$message->toolCallId ?? ''] ?? null;
                $result = json_decode($message->content, true);

                if (! $call || ! is_array($result) || (array_is_list($result) && $result !== [])) {
                    throw AIException::invalidResponse();
                }

                $functionResponse = ['name' => $call['name'], 'response' => $result];

                if ($call['remote_id'] !== null) {
                    $functionResponse['id'] = $call['remote_id'];
                }

                $pendingResults[] = ['functionResponse' => $functionResponse];

                continue;
            }

            if ($pendingResults !== []) {
                $contents[] = ['role' => 'user', 'parts' => $pendingResults];
                $pendingResults = [];
            }

            if ($message->role === 'assistant' && $message->toolCalls !== []) {
                $raw = $message->providerState ? base64_decode($message->providerState, true) : false;
                $parts = is_string($raw) ? json_decode($raw, true) : null;

                if (! is_array($parts) || ! array_is_list($parts) || $parts === []) {
                    throw AIException::invalidResponse();
                }

                foreach ($message->toolCalls as $call) {
                    $callNames[$call->id] = [
                        'name' => $call->name,
                        'remote_id' => str_starts_with($call->id, 'gemini-local-') ? null : $call->id,
                    ];
                }

                $contents[] = ['role' => 'model', 'parts' => $parts];
            } elseif (in_array($message->role, ['user', 'assistant'], true)) {
                $contents[] = ['role' => $message->role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $message->content]]];
            } else {
                throw AIException::invalidResponse();
            }
        }

        if ($pendingResults !== []) {
            $contents[] = ['role' => 'user', 'parts' => $pendingResults];
        }

        return [$system, $contents];
    }

    /** @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function geminiSchema(array $schema): array
    {
        $converted = array_intersect_key($schema, array_flip(['type', 'description', 'enum', 'required']));

        if (isset($schema['properties']) && is_array($schema['properties']) && $schema['properties'] !== []) {
            $properties = [];

            foreach ($schema['properties'] as $name => $property) {
                $properties[$name] = $this->geminiSchema($property);
            }

            $converted['properties'] = $properties;
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $converted['items'] = $this->geminiSchema($schema['items']);
        }

        return $converted;
    }

    private function supportsAgentModel(string $model): bool
    {
        return in_array($model, self::AGENT_MODELS, true);
    }

    private function logToolRequestFailure(Response $response, AIToolRequest $request): void
    {
        $message = $response->json('error.message');

        if (is_string($message)) {
            $message = preg_replace('/\s+/', ' ', $message);
            $message = preg_replace('/(?:key=|api[_ -]?key[=: ]+|bearer\s+)[^\s&,]+/i', '[redacted]', $message);
            $message = preg_replace('/AIza[0-9A-Za-z_-]{20,}/', '[redacted]', $message);
            $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $message);
            $message = mb_substr($message, 0, 400);
        }

        $googleStatus = $response->json('error.status');
        $googleCode = $response->json('error.code');

        Log::warning('Gemini assistant request failed', [
            'stage' => $request->requireTool ? 'initial_tool_declaration' : 'tool_result_roundtrip',
            'model' => $request->model,
            'api_route' => 'v1beta/models:generateContent',
            'http_status' => $response->status(),
            'google_status' => is_string($googleStatus) ? mb_substr($googleStatus, 0, 80) : null,
            'google_code' => is_int($googleCode) ? $googleCode : null,
            'google_message' => $message,
        ]);
    }
}
