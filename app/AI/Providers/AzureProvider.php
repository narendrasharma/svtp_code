<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIToolCallingProviderInterface;
use App\AI\DTOs\AIMessage;
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

class AzureProvider implements AIToolCallingProviderInterface
{
    public function __construct(private readonly AISettings $settings) {}

    public function key(): string
    {
        return 'azure';
    }

    public function capabilities(): ProviderCapabilities
    {
        if ($this->settings->azureEndpoint() === '' || $this->settings->model($this->key()) === '' || ! $this->settings->hasCredential($this->key())) {
            return new ProviderCapabilities([]);
        }

        return new ProviderCapabilities($this->settings->azureToolCallingEnabled()
            ? ['text_generation', 'chat', 'tool_calling'] : ['text_generation', 'chat']);
    }

    public function generate(AIRequest $request): AIResponse
    {
        if (! $request->model) {
            throw AIException::unconfigured();
        }

        $payload = [
            'model' => $request->model,
            'input' => $request->userContent,
            'store' => false,
        ];

        if ($request->systemInstructions) {
            $payload['instructions'] = $request->systemInstructions;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $payload['max_output_tokens'] = $request->maxTokens;
        }

        $response = $this->send($payload);
        $text = $this->responseText($response);

        if ($text === '') {
            throw AIException::invalidResponse();
        }

        return new AIResponse(
            text: $text,
            provider: $this->key(),
            model: $this->reportedModel($response, $request->model),
            usage: $this->usage($response),
            finishStatus: is_string($response->json('status')) ? $response->json('status') : null,
            requestId: $response->header('apim-request-id') ?: $response->header('x-request-id'),
        );
    }

    public function chatWithTools(AIToolRequest $request): AIToolResponse
    {
        if (! $request->model) {
            throw AIException::unconfigured();
        }

        if (! $this->capabilities()->supports('tool_calling')) {
            throw AIException::unsupportedTools();
        }

        $input = [];

        foreach ($request->messages as $message) {
            array_push($input, ...$this->inputItems($message));
        }

        $payload = [
            'model' => $request->model,
            'input' => $input,
            'tools' => array_map(fn (array $tool): array => [
                'type' => 'function',
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['input_schema'],
                'strict' => false,
            ], $request->tools),
            'tool_choice' => $request->requireTool ? 'required' : ($request->tools === [] ? 'none' : 'auto'),
            'max_output_tokens' => 900,
            'store' => false,
            'include' => ['reasoning.encrypted_content'],
        ];

        $response = $this->send($payload);
        $output = $response->json('output');

        if (! is_array($output) || ! array_is_list($output)) {
            throw AIException::invalidResponse();
        }

        $calls = [];
        $seenCallIds = [];

        foreach ($output as $item) {
            if (! is_array($item)) {
                throw AIException::invalidResponse();
            }

            if (($item['type'] ?? null) !== 'function_call') {
                continue;
            }

            $arguments = is_string($item['arguments'] ?? null) ? json_decode($item['arguments'], true) : null;

            if (! is_string($item['call_id'] ?? null) || $item['call_id'] === ''
                || ! is_string($item['name'] ?? null) || $item['name'] === ''
                || ! is_array($arguments) || (array_is_list($arguments) && $arguments !== [])
                || isset($seenCallIds[$item['call_id']])) {
                throw AIException::invalidResponse();
            }

            $seenCallIds[$item['call_id']] = true;
            $calls[] = new AIToolCall($item['call_id'], $item['name'], $arguments);
        }

        $text = $this->responseText($response);

        if ($calls === [] && $text === '') {
            throw AIException::invalidResponse();
        }

        return new AIToolResponse(
            $text, $calls, $this->key(), $this->reportedModel($response, $request->model),
            $this->usage($response), $calls !== [] ? json_encode($output, JSON_INVALID_UTF8_SUBSTITUTE) : null,
        );
    }

    /** @return list<array<string, mixed>> */
    private function inputItems(AIMessage $message): array
    {
        if ($message->role === 'tool') {
            return [['type' => 'function_call_output', 'call_id' => $message->toolCallId, 'output' => $message->content]];
        }

        if ($message->role === 'assistant' && $message->toolCalls !== []) {
            $state = is_string($message->providerState) ? json_decode($message->providerState, true) : null;

            if (is_array($state) && array_is_list($state)) {
                return $state;
            }

            $items = $message->content !== '' ? [['type' => 'message', 'role' => 'assistant', 'content' => $message->content]] : [];

            foreach ($message->toolCalls as $call) {
                $items[] = [
                    'type' => 'function_call', 'call_id' => $call->id,
                    'name' => $call->name, 'arguments' => json_encode($call->arguments),
                ];
            }

            return $items;
        }

        return [['type' => 'message', 'role' => $message->role, 'content' => $message->content]];
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload): Response
    {
        $endpoint = $this->settings->azureEndpoint();
        $credential = $this->settings->credential($this->key());

        if ($endpoint === '' || ! $credential) {
            throw AIException::unconfigured();
        }

        $url = $this->responsesUrl($endpoint);

        try {
            $response = Http::withHeaders(['api-key' => $credential])
                ->connectTimeout(5)->timeout(15)
                ->retry(2, 200, fn (Throwable $exception, PendingRequest $pending): bool => $exception instanceof RequestException
                    && in_array($exception->response->status(), [429, 500, 502, 503, 504], true)
                    && ! in_array(strtolower((string) $exception->response->json('error.code')), [
                        'insufficient_quota', 'quota_exceeded', 'out_of_quota', 'billing_hard_limit_reached',
                    ], true), throw: false)
                ->post($url, $payload);
        } catch (ConnectionException) {
            throw AIException::timeout();
        }

        if ($response->failed()) {
            $this->logFailure($response, $payload, $url, $credential);

            throw $this->failure($response, isset($payload['tools']));
        }

        if ($response->json('status') === 'failed' || $response->json('status') === 'incomplete') {
            throw AIException::invalidResponse();
        }

        return $response;
    }

    private function responsesUrl(string $endpoint): string
    {
        $url = rtrim($endpoint, '/');
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['port'])
            || ! (str_ends_with($host, '.openai.azure.com') || str_ends_with($host, '.services.ai.azure.com'))
            || ! preg_match('~^(?:|/api/projects/[A-Za-z0-9_-]+)(?:/openai/v1)?$~', $path)) {
            throw AIException::invalidProviderRequest();
        }

        return $url.(str_ends_with($path, '/openai/v1') ? '' : '/openai/v1').'/responses';
    }

    private function failure(Response $response, bool $withTools): AIException
    {
        $code = strtolower((string) ($response->json('error.code') ?: $response->json('code') ?: ''));
        $message = strtolower(mb_substr((string) $response->json('error.message'), 0, 250));
        $toolsUnsupported = $withTools && (in_array($code, ['unsupported_feature', 'unsupported_tools', 'tool_calling_not_supported', 'operationnotsupported'], true)
            || preg_match('/(?:tool|function calling).{0,100}(?:not supported|unsupported)|(?:not supported|unsupported).{0,100}(?:tool|function calling)/', $message));

        if (in_array($code, ['insufficient_quota', 'quota_exceeded', 'out_of_quota', 'billing_hard_limit_reached'], true)) {
            return AIException::quotaExhausted();
        }

        return match ($response->status()) {
            400, 422 => $toolsUnsupported ? AIException::unsupportedTools() : AIException::invalidProviderRequest(),
            401 => AIException::authentication(),
            403 => AIException::providerAccessDenied(),
            404 => AIException::deploymentUnavailable(),
            408, 504 => AIException::timeout(),
            429 => AIException::rateLimited(),
            default => AIException::unavailable(),
        };
    }

    /** @param array<string, mixed> $payload */
    private function logFailure(Response $response, array $payload, string $url, string $credential): void
    {
        $error = $response->json('error');
        $error = is_array($error) ? $error : [];
        $message = is_string($error['message'] ?? null) ? $error['message'] : '';
        $message = str_replace($credential, '[redacted]', $message);
        $message = preg_replace('/(?:api[-_ ]?key|bearer)\s*[:= ]+\S+/i', '[redacted credential]', $message) ?? '';
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '';
        $hasToolOutput = false;

        foreach (is_array($payload['input'] ?? null) ? $payload['input'] : [] as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'function_call_output') {
                $hasToolOutput = true;

                break;
            }
        }

        Log::warning('Azure Responses request failed', [
            'http_status' => $response->status(),
            'azure_error_code' => is_scalar($error['code'] ?? null) ? mb_substr((string) $error['code'], 0, 80) : null,
            'azure_error_type' => is_scalar($error['type'] ?? null) ? mb_substr((string) $error['type'], 0, 80) : null,
            'safe_message' => mb_substr($message, 0, 250),
            'request_stage' => isset($payload['tools']) ? ($hasToolOutput ? 'tool_result_roundtrip' : 'tool_declaration') : 'content_generation',
            'endpoint_route' => parse_url($url, PHP_URL_PATH),
            'deployment' => mb_substr((string) ($payload['model'] ?? ''), 0, 100),
        ]);
    }

    private function responseText(Response $response): string
    {
        $text = $response->json('output_text');

        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        $parts = [];

        $output = $response->json('output');

        if (! is_array($output)) {
            return '';
        }

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'output_text' && is_string($part['text'] ?? null)) {
                    $parts[] = $part['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    /** @return array<string, int>|null */
    private function usage(Response $response): ?array
    {
        $usage = $response->json('usage');

        return is_array($usage) ? array_filter([
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
        ], 'is_int') : null;
    }

    private function reportedModel(Response $response, string $requestedModel): string
    {
        $reported = $response->json('model');

        return is_string($reported) && $reported !== '' ? $reported : $requestedModel;
    }
}
