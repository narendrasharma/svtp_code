<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AIExecutionContext;
use App\Models\AiKnowledgeDocument;

interface VectorStoreInterface
{
    /** @param list<array{content:string,embedding:list<float>}> $chunks */
    public function replace(AiKnowledgeDocument $document, array $chunks, string $provider, string $model): void;

    public function hasCandidates(string $provider, string $model, AIExecutionContext $context): bool;

    /** @param list<float> $embedding
     * @return list<array<string,mixed>>
     */
    public function search(array $embedding, string $provider, string $model, string $query, AIExecutionContext $context, int $limit): array;
}
