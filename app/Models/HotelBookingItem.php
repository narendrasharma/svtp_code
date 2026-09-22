<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelBookingItem extends Model
{
    use HasFactory;

    protected $fillable = ['hotel_booking_id', 'room_type_id', 'rate_plan_id', 'room_type_name_snapshot', 'rate_plan_name_snapshot', 'meal_plan_snapshot', 'cancellation_mode_snapshot', 'quantity', 'adults', 'children', 'check_in', 'check_out', 'nights', 'currency', 'subtotal', 'taxes', 'fees', 'total', 'pricing_snapshot', 'status'];

    protected function casts(): array
    {
        return ['check_in' => 'date', 'check_out' => 'date', 'pricing_snapshot' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(HotelRatePlan::class);
    }

    public function reservationNights(): HasMany
    {
        return $this->hasMany(HotelReservationNight::class);
    }
}
