<?php

namespace App\Models;

use App\Enums\CommunicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'user_id', 'channel', 'status', 'sent_at', 'error',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): CommunicationStatus
    {
        return CommunicationStatus::from($this->status);
    }
}
