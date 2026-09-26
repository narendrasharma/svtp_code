<?php

namespace App\AI\Support;

use App\AI\Contracts\VectorStoreInterface;
use App\Models\AiKnowledgeDocument;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Place;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KnowledgeIndexingService
{
    public const MAX_DOCUMENT_LENGTH = 12000;

    public const BATCH_SIZE = 10;

    public function __construct(private readonly EmbeddingManager $embeddings, private readonly VectorStoreInterface $store) {}

    /** @return array{indexed:bool,chunks:int} */
    public function index(AiKnowledgeDocument $document): array
    {
        $content = $this->plainText($document->content);

        if ($content === '' || mb_strlen($content) > self::MAX_DOCUMENT_LENGTH) {
            throw ValidationException::withMessages(['content' => 'Knowledge content must be between 1 and 12,000 characters.']);
        }

        $provider = $this->embeddings->provider();
        $model = $this->embeddings->model();
        $hash = hash('sha256', $document->title."\n".$content);
        $firstChunk = $document->chunks()->first();

        if ($document->content_hash === $hash && $firstChunk?->embedding_provider === $provider->key()
            && $firstChunk->embedding_model === $model) {
            if ($document->status !== 'active') {
                $document->update(['status' => 'active']);
            }

            return ['indexed' => false, 'chunks' => $document->chunks()->count()];
        }

        $chunks = [];

        foreach ($this->split($document->title."\n".$content) as $piece) {
            if ($chunks !== []) {
                usleep(150000);
            }

            $chunks[] = ['content' => $piece, 'embedding' => $this->embeddings->embed($piece, 'document', $document->title)];
        }

        DB::transaction(function () use ($document, $chunks, $provider, $model, $hash): void {
            $this->store->replace($document, $chunks, $provider->key(), $model);
            $document->forceFill(['content_hash' => $hash, 'last_indexed_at' => now(), 'status' => 'active'])->save();
        });

        return ['indexed' => true, 'chunks' => count($chunks)];
    }

    public function import(string $type, int $sourceId, int $userId): AiKnowledgeDocument
    {
        $class = match ($type) {
            'page' => Page::class,
            'destination' => Destination::class,
            'place' => Place::class,
            default => throw ValidationException::withMessages(['source_type' => 'Unsupported knowledge source.']),
        };
        $source = $class::query()->whereKey($sourceId)->where('is_active', true)->firstOrFail();
        $title = (string) ($type === 'page' ? $source->title : $source->name);
        $content = trim((string) ($source->excerpt ?? '')."\n".(string) ($type === 'page' ? $source->content : $source->description));
        $plain = $this->plainText($content);

        if ($plain === '' || mb_strlen($plain) > self::MAX_DOCUMENT_LENGTH || mb_strlen($content) > 24000) {
            throw ValidationException::withMessages(['source_id' => 'Source has no suitable content or exceeds 12,000 characters.']);
        }

        $content = $plain;

        $document = AiKnowledgeDocument::firstOrNew(['source_type' => $type, 'source_id' => $sourceId]);
        $changed = $document->exists && ($document->title !== mb_substr($title, 0, 200) || $document->content !== $content);

        if ($changed) {
            $document->update(['status' => 'inactive']);
            $document->chunks()->delete();
        }

        $document->fill([
            'title' => mb_substr($title, 0, 200), 'content' => $content,
            'visibility' => 'public', 'locale' => $source->locale ?? null, 'created_by' => $document->created_by ?: $userId,
        ]);
        $document->save();
        $this->index($document);

        return $document;
    }

    /** @return array{processed:int,next_id:int|null} */
    public function importBatch(string $type, ?int $afterId, int $userId): array
    {
        $class = match ($type) {
            'page' => Page::class, 'destination' => Destination::class, 'place' => Place::class,
            default => throw ValidationException::withMessages(['source_type' => 'Unsupported knowledge source.']),
        };
        $rows = $class::query()->where('is_active', true)->when($afterId, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')->limit(self::BATCH_SIZE)->get();
        $processed = 0;

        foreach ($rows as $row) {
            try {
                $this->import($type, $row->id, $userId);
                $processed++;
            } catch (ValidationException) {
                continue;
            }
        }

        return ['processed' => $processed, 'next_id' => $rows->count() === self::BATCH_SIZE ? $rows->last()->id : null];
    }

    private function plainText(string $content): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /** @return list<string> */
    private function split(string $content): array
    {
        $chunks = [];
        $length = mb_strlen($content);

        for ($offset = 0; $offset < $length && count($chunks) < 20; $offset += 880) {
            $chunks[] = mb_substr($content, $offset, 1000);
        }

        return $chunks;
    }
}
