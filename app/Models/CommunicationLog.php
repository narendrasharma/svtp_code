<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_user_id', 'recipient_type', 'channel',
        'template_key', 'event',
        'related_type', 'related_id',
        'destination_masked', 'status', 'provider',
        'error', 'sent_at', 'failed_at',
        'created_by', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function channel(): CommunicationChannel
    {
        return CommunicationChannel::from($this->channel);
    }

    public function status(): CommunicationStatus
    {
        return CommunicationStatus::from($this->status);
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    public static function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return '***'.substr($digits, -4);
    }
}
