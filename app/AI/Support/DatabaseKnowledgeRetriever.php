<?php

namespace App\AI\Support;

use App\AI\Contracts\KnowledgeRetrieverInterface;
use App\AI\Contracts\VectorStoreInterface;
use App\AI\DTOs\AIExecutionContext;

class DatabaseKnowledgeRetriever implements KnowledgeRetrieverInterface
{
    public function __construct(private readonly EmbeddingManager $embeddings, private readonly VectorStoreInterface $store) {}

    public function search(string $query, AIExecutionContext $context, int $limit = 5): array
    {
        $query = trim(strip_tags($query));

        if ($query === '' || mb_strlen($query) > 300 || $context->role !== 'admin') {
            return [];
        }

        $provider = $this->embeddings->provider();
        $model = $this->embeddings->model();

        $fallback = new AIExecutionContext(
            $context->userId, $context->role, $context->permissions, $context->vendorId, null, $context->currency,
        );
        $matchingLocale = $this->store->hasCandidates($provider->key(), $model, $context);

        if (! $matchingLocale && ! $this->store->hasCandidates($provider->key(), $model, $fallback)) {
            return [];
        }

        $vector = $this->embeddings->embed($query, 'query');
        $results = $matchingLocale ? $this->store->search($vector, $provider->key(), $model, $query, $context, $limit) : [];

        if ($results === [] && $context->locale !== null) {
            return $this->store->search($vector, $provider->key(), $model, $query, $fallback, $limit);
        }

        return $results;
    }
}
