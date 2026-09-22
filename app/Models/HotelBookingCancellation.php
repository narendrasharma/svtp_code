<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingCancellation extends Model
{
    use HasFactory;

    protected $fillable = ['hotel_booking_id', 'idempotency_key', 'reason_code', 'note', 'currency', 'cancellation_fee', 'refundable_amount', 'policy_snapshot', 'cancelled_at', 'cancelled_by'];

    protected function casts(): array
    {
        return ['cancellation_fee' => 'decimal:2', 'refundable_amount' => 'decimal:2', 'policy_snapshot' => 'array', 'cancelled_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
