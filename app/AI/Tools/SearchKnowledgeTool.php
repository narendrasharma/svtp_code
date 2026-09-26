<?php

namespace App\AI\Tools;

use App\AI\Contracts\AIToolInterface;
use App\AI\Contracts\KnowledgeRetrieverInterface;
use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\AIException;
use App\AI\Support\AIToolAction;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class SearchKnowledgeTool implements AIToolInterface
{
    public function __construct(private readonly KnowledgeRetrieverInterface $retriever) {}

    public function name(): string
    {
        return 'search_knowledge';
    }

    public function description(): string
    {
        return 'Search indexed policies, CMS pages, destination and place descriptions. Never use for live bookings, prices, inventory or counts.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'query' => ['type' => 'string', 'description' => 'Focused policy or travel knowledge question'],
            'limit' => ['type' => 'integer'],
        ], 'required' => ['query']];
    }

    public function action(): AIToolAction
    {
        return AIToolAction::Read;
    }

    public function requiredPermission(): string
    {
        return 'ai.assistant.use';
    }

    public function execute(AIExecutionContext $context, array $arguments): array
    {
        if ($context->role !== 'admin' || $context->userId === null
            || ! in_array($this->requiredPermission(), $context->permissions, true)
            || array_diff(array_keys($arguments), ['query', 'limit']) !== []
            || Validator::make($arguments, ['query' => 'required|string|max:300', 'limit' => 'sometimes|integer|between:1,6'])->fails()) {
            throw new InvalidArgumentException('Invalid or unavailable knowledge search.');
        }

        try {
            return ['available' => true, 'items' => $this->retriever->search($arguments['query'], $context, (int) ($arguments['limit'] ?? 5))];
        } catch (AIException $exception) {
            $reason = match ($exception->reason) {
                'rate_limited' => 'embedding_rate_limited',
                'timeout' => 'embedding_timeout',
                'disabled', 'unconfigured' => 'embedding_unconfigured',
                default => 'embedding_unavailable',
            };

            return [
                'available' => false,
                'items' => [],
                'reason' => $reason,
                'error' => match ($reason) {
                    'embedding_rate_limited' => 'Knowledge embedding rate limit reached; live marketplace tools are still available.',
                    'embedding_timeout' => 'Knowledge embedding timed out; live marketplace tools are still available.',
                    'embedding_unconfigured' => 'Knowledge search is not configured; live marketplace tools are still available.',
                    default => 'Knowledge embedding is temporarily unavailable; live marketplace tools are still available.',
                },
            ];
        }
    }
}
