<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AIExecutionContext;

interface KnowledgeRetrieverInterface
{
    /** @return list<array<string, mixed>> */
    public function search(string $query, AIExecutionContext $context, int $limit = 5): array;
}
