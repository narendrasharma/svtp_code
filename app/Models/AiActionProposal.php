<?php

namespace App\Models;

use Database\Factories\AiActionProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionProposal extends Model
{
    /** @use HasFactory<AiActionProposalFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'ai_conversation_id', 'tool_name', 'target_type', 'target_id', 'target_label', 'field_label',
        'summary', 'risk_level', 'status', 'validated_arguments', 'before_snapshot',
        'proposed_changes', 'result', 'failure_reason', 'expires_at', 'confirmed_at', 'executed_at',
    ];

    protected $casts = [
        'validated_arguments' => 'array', 'before_snapshot' => 'array', 'proposed_changes' => 'array',
        'result' => 'array', 'expires_at' => 'datetime', 'confirmed_at' => 'datetime', 'executed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
