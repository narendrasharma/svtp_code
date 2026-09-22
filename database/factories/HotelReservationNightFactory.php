<?php

namespace Database\Factories;

use App\Models\HotelBookingItem;
use App\Models\HotelReservationNight;
use App\Models\HotelRoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HotelReservationNight> */
class HotelReservationNightFactory extends Factory
{
    protected $model = HotelReservationNight::class;

    public function definition(): array
    {
        return ['hotel_booking_item_id' => HotelBookingItem::factory(), 'room_type_id' => HotelRoomType::factory(), 'stay_date' => now()->addDays(30)->toDateString(), 'quantity' => 1, 'status' => 'confirmed'];
    }
}
