<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingChange extends Model
{
    use HasFactory;

    protected $fillable = ['hotel_booking_id', 'idempotency_key', 'old_check_in', 'old_check_out', 'new_check_in', 'new_check_out', 'old_total', 'new_total', 'difference', 'currency', 'status', 'reason', 'old_snapshot', 'new_snapshot', 'requested_by'];

    protected function casts(): array
    {
        return ['old_check_in' => 'date', 'old_check_out' => 'date', 'new_check_in' => 'date', 'new_check_out' => 'date', 'old_total' => 'decimal:2', 'new_total' => 'decimal:2', 'difference' => 'decimal:2', 'old_snapshot' => 'array', 'new_snapshot' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
