<?php

namespace App\Models;

use App\Enums\CancellationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCancellationRequest extends Model
{
    protected $fillable = [
        'booking_id', 'user_id', 'reason', 'status',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'status' => CancellationStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === CancellationStatus::Pending;
    }
}
