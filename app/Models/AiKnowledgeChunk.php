<?php

namespace App\Models;

use Database\Factories\AiKnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiKnowledgeChunk extends Model
{
    /** @use HasFactory<AiKnowledgeChunkFactory> */
    use HasFactory;

    protected $fillable = [
        'chunk_index', 'content', 'embedding', 'embedding_provider', 'embedding_model', 'content_hash',
    ];

    protected $casts = ['embedding' => 'array'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeDocument::class, 'ai_knowledge_document_id');
    }
}
