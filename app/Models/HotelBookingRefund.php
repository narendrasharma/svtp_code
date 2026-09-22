<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingRefund extends Model
{
    use HasFactory;

    protected $fillable = ['hotel_booking_id', 'hotel_booking_cancellation_id', 'refund_number', 'idempotency_key', 'amount', 'currency', 'status', 'reason', 'payment_reference', 'initiated_by', 'metadata', 'requested_at', 'completed_at', 'failed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'metadata' => 'array', 'requested_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(HotelBookingCancellation::class, 'hotel_booking_cancellation_id');
    }
}
