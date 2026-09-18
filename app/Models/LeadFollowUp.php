<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'assigned_to', 'due_at', 'type', 'status',
        'note', 'completed_at', 'created_by',
        // Phase 11.5D idempotency markers (stamped by reminder jobs).
        'reminder_sent_at', 'overdue_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'overdue_reminder_sent_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === FollowUpStatus::Pending->value && $this->due_at->isPast();
    }

    public function scopePending($query)
    {
        return $query->where('status', FollowUpStatus::Pending->value);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', FollowUpStatus::Pending->value)->where('due_at', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', FollowUpStatus::Pending->value)->whereDate('due_at', today());
    }
}
