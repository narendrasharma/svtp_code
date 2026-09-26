<?php

namespace App\AI\Support;

use App\AI\Contracts\VectorStoreInterface;
use App\AI\DTOs\AIExecutionContext;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Place;

class DatabaseVectorStore implements VectorStoreInterface
{
    private const MAX_CANDIDATES = 500;

    /** @param list<array{content:string,embedding:list<float>}> $chunks */
    public function replace(AiKnowledgeDocument $document, array $chunks, string $provider, string $model): void
    {
        $document->chunks()->delete();

        foreach ($chunks as $index => $chunk) {
            $document->chunks()->create([
                'chunk_index' => $index,
                'content' => $chunk['content'],
                'embedding' => $chunk['embedding'],
                'embedding_provider' => $provider,
                'embedding_model' => $model,
                'content_hash' => hash('sha256', $chunk['content']),
            ]);
        }
    }

    public function hasCandidates(string $provider, string $model, AIExecutionContext $context): bool
    {
        return $this->candidates($provider, $model, $context)->exists();
    }

    public function search(array $embedding, string $provider, string $model, string $query, AIExecutionContext $context, int $limit): array
    {
        $rows = $this->candidates($provider, $model, $context)
            ->with('document:id,title,source_type,source_id,locale,visibility,status')
            ->orderByDesc('id')->limit(self::MAX_CANDIDATES)->get();
        $results = [];
        $sourceActive = [];

        foreach ($rows as $chunk) {
            $document = $chunk->document;

            if (! $document) {
                continue;
            }

            $sourceKey = $document->source_type.':'.$document->source_id;
            $sourceActive[$sourceKey] ??= $this->sourceIsActive($document);

            if (! $sourceActive[$sourceKey]) {
                continue;
            }

            if (! is_array($chunk->embedding) || count($embedding) !== count($chunk->embedding)) {
                continue;
            }

            $score = $this->cosine($embedding, $chunk->embedding ?? []);
            $needle = mb_strtolower($query);
            $haystack = mb_strtolower($document->title.' '.$chunk->content);

            if (mb_stripos($haystack, $needle) !== false) {
                $score += 0.2;
            }

            if ($score < 0.08) {
                continue;
            }

            $results[] = [
                'document_id' => $document->id,
                'title' => mb_substr($document->title, 0, 200),
                'source_type' => $document->source_type,
                'source_id' => $document->source_id,
                'locale' => $document->locale,
                'chunk' => mb_substr($chunk->content, 0, 1000),
                'score' => round($score, 3),
            ];
        }

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, max(1, min(6, $limit)));
    }

    private function candidates(string $provider, string $model, AIExecutionContext $context)
    {
        $mayReadInternal = in_array('ai.knowledge.manage', $context->permissions, true);

        return AiKnowledgeChunk::query()
            ->where('embedding_provider', $provider)
            ->where('embedding_model', $model)
            ->whereHas('document', function ($query) use ($context, $mayReadInternal): void {
                $query->where('status', 'active');

                if (! $mayReadInternal) {
                    $query->where('visibility', 'public');
                }

                if ($context->locale) {
                    $query->where(fn ($q) => $q->whereNull('locale')->orWhere('locale', $context->locale));
                }
            });
    }

    private function sourceIsActive(AiKnowledgeDocument $document): bool
    {
        $model = match ($document->source_type) {
            'page' => Page::class,
            'destination' => Destination::class,
            'place' => Place::class,
            'manual' => null,
            default => false,
        };

        if ($model === false) {
            return false;
        }

        if ($model === null) {
            return true;
        }

        return $model::whereKey($document->source_id)->where('is_active', true)->exists();
    }

    /** @param list<float> $a
     * @param  list<float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === []) {
            return 0.0;
        }

        $dot = $left = $right = 0.0;

        foreach ($a as $index => $value) {
            $dot += $value * $b[$index];
            $left += $value * $value;
            $right += $b[$index] * $b[$index];
        }

        return $left > 0 && $right > 0 ? $dot / sqrt($left * $right) : 0.0;
    }
}
