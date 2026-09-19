<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal operational note on a taxi booking (12A.3 dispatch).
 * Internal only — never exposed to customer surfaces.
 */
class TaxiBookingNote extends Model
{
    use HasFactory;

    protected $fillable = ['taxi_booking_id', 'author_id', 'body', 'visible_to_driver'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
