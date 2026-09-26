<?php

namespace App\Models;

use Database\Factories\AiToolEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolEvent extends Model
{
    /** @use HasFactory<AiToolEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['tool_name', 'succeeded', 'duration_ms', 'result_count', 'created_at'];

    protected $casts = ['succeeded' => 'boolean', 'created_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
