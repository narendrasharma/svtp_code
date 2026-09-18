<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only lifecycle log for a booking.
 *
 * Status values are stored as plain strings (never enum casts) so history
 * rows remain readable even if a status is retired in future versions.
 */
class BookingStatusHistory extends Model
{
    protected $fillable = [
        'booking_id', 'from_status', 'to_status',
        'payment_from', 'payment_to', 'changed_by', 'note',
        'is_internal',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
