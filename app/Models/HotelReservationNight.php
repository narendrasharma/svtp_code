<?php

namespace App\Models;

use App\Enums\HotelReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReservationNight extends Model
{
    use HasFactory;

    protected $fillable = ['hotel_booking_item_id', 'room_type_id', 'stay_date', 'quantity', 'status'];

    protected function casts(): array
    {
        return ['stay_date' => 'date', 'status' => HotelReservationStatus::class];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(HotelBookingItem::class, 'hotel_booking_item_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class);
    }

    public static function consumingStatuses(): array
    {
        return [HotelReservationStatus::Held->value, HotelReservationStatus::Pending->value, HotelReservationStatus::Confirmed->value, HotelReservationStatus::CheckedIn->value];
    }
}
