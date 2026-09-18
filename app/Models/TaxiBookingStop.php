<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxiBookingStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxi_booking_id', 'stop_type', 'address', 'lat', 'lng', 'notes', 'sort_order',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }
}
