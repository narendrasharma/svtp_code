<?php

namespace App\Models;

use Database\Factories\AiKnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiKnowledgeDocument extends Model
{
    /** @use HasFactory<AiKnowledgeDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'source_type', 'source_id', 'content', 'status', 'visibility',
        'locale', 'content_hash', 'last_indexed_at', 'created_by',
    ];

    protected $casts = ['last_indexed_at' => 'datetime'];

    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class);
    }
}
