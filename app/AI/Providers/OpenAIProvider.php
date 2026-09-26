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
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements AIToolCallingProviderInterface
{
    public function __construct(private readonly AISettings $settings) {}

    public function key(): string
    {
        return 'openai';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(['text_generation', 'chat', 'tool_calling']);
    }

    public function generate(AIRequest $request): AIResponse
    {
        $credential = $this->settings->credential($this->key());

        if (! $credential || ! $request->model) {
            throw AIException::unconfigured();
        }

        $messages = [];

        if ($request->systemInstructions) {
            $messages[] = ['role' => 'system', 'content' => $request->systemInstructions];
        }

        $messages[] = ['role' => 'user', 'content' => $request->userContent];
        $payload = ['model' => $request->model, 'messages' => $messages];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $payload['max_completion_tokens'] = $request->maxTokens;
        }

        try {
            $response = Http::withToken($credential)
                ->connectTimeout(5)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (ConnectionException) {
            throw AIException::timeout();
        }

        if ($response->failed()) {
            throw AIException::fromHttpStatus($response->status());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw AIException::invalidResponse();
        }

        $usage = $response->json('usage');
        $reportedModel = $response->json('model');
        $finishStatus = $response->json('choices.0.finish_reason');

        return new AIResponse(
            text: $text,
            provider: $this->key(),
            model: is_string($reportedModel) && $reportedModel !== '' ? $reportedModel : $request->model,
            usage: is_array($usage) ? array_filter([
                'input_tokens' => $usage['prompt_tokens'] ?? null,
                'output_tokens' => $usage['completion_tokens'] ?? null,
                'total_tokens' => $usage['total_tokens'] ?? null,
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

        $messages = [];

        foreach ($request->messages as $message) {
            if ($message->role === 'assistant' && $message->toolCalls !== []) {
                $messages[] = [
                    'role' => 'assistant', 'content' => $message->content ?: null,
                    'tool_calls' => array_map(fn (AIToolCall $call): array => [
                        'id' => $call->id, 'type' => 'function',
                        'function' => ['name' => $call->name, 'arguments' => json_encode($call->arguments)],
                    ], $message->toolCalls),
                ];
            } elseif ($message->role === 'tool') {
                $messages[] = ['role' => 'tool', 'tool_call_id' => $message->toolCallId, 'content' => $message->content];
            } else {
                $messages[] = ['role' => $message->role, 'content' => $message->content];
            }
        }

        $payload = [
            'model' => $request->model, 'messages' => $messages,
            'tools' => array_map(fn (array $tool): array => [
                'type' => 'function',
                'function' => ['name' => $tool['name'], 'description' => $tool['description'], 'parameters' => $tool['input_schema']],
            ], $request->tools),
            'tool_choice' => $request->requireTool ? 'required' : ($request->tools === [] ? 'none' : 'auto'),
            'max_completion_tokens' => 900,
        ];

        try {
            $response = Http::withToken($credential)->connectTimeout(5)->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (ConnectionException) {
            throw AIException::timeout();
        }

        if ($response->failed()) {
            throw AIException::fromHttpStatus($response->status());
        }

        $rawCalls = $response->json('choices.0.message.tool_calls') ?? [];

        if (! is_array($rawCalls)) {
            throw AIException::invalidResponse();
        }

        $calls = [];

        foreach ($rawCalls as $rawCall) {
            if (! is_array($rawCall) || ! is_array($rawCall['function'] ?? null)
                || ! is_string($rawCall['function']['arguments'] ?? null)) {
                throw AIException::invalidResponse();
            }

            $arguments = json_decode($rawCall['function']['arguments'] ?? '', true);

            if (($rawCall['type'] ?? null) !== 'function' || ! is_string($rawCall['id'] ?? null)
                || ! is_string($rawCall['function']['name'] ?? null) || ! is_array($arguments) || array_is_list($arguments) && $arguments !== []) {
                throw AIException::invalidResponse();
            }

            $calls[] = new AIToolCall($rawCall['id'], $rawCall['function']['name'], $arguments);
        }

        $text = $response->json('choices.0.message.content');

        if ($calls === [] && (! is_string($text) || trim($text) === '')) {
            throw AIException::invalidResponse();
        }

        $usage = $response->json('usage');

        return new AIToolResponse(
            is_string($text) ? $text : '', $calls, $this->key(),
            (string) ($response->json('model') ?: $request->model),
            is_array($usage) ? array_filter([
                'input_tokens' => $usage['prompt_tokens'] ?? null,
                'output_tokens' => $usage['completion_tokens'] ?? null,
                'total_tokens' => $usage['total_tokens'] ?? null,
            ], 'is_int') : null,
        );
    }
}
