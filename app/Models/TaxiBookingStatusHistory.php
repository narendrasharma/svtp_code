<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxiBookingStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxi_booking_id', 'from_status', 'to_status', 'changed_by', 'note',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
