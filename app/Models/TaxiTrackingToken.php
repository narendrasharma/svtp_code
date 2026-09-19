<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer tracking token (Phase 12A.9).
 *
 * Only the SHA-256 hash is persisted — the raw token is displayed once
 * at generation and is never recoverable. Single-active-token model:
 * generating a new token revokes prior active ones for the booking.
 * Deliberately NOT in the audit-trail registry; lifecycle events are
 * logged explicitly by the tracking service.
 */
class TaxiTrackingToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxi_booking_id', 'token_hash', 'expires_at', 'revoked_at',
        'last_accessed_at', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
