<?php

namespace Database\Factories;

use App\Models\HotelBooking;
use App\Models\HotelBookingItem;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HotelBookingItem> */
class HotelBookingItemFactory extends Factory
{
    protected $model = HotelBookingItem::class;

    public function definition(): array
    {
        $in = now()->addDays(30)->toDateString();
        $out = now()->addDays(32)->toDateString();

        return ['hotel_booking_id' => HotelBooking::factory(), 'room_type_id' => HotelRoomType::factory(), 'rate_plan_id' => HotelRatePlan::factory(), 'room_type_name_snapshot' => 'Deluxe Room', 'rate_plan_name_snapshot' => 'Room Only', 'quantity' => 1, 'adults' => 2, 'children' => 0, 'check_in' => $in, 'check_out' => $out, 'nights' => 2, 'currency' => 'USD', 'subtotal' => '200.00', 'taxes' => '20.00', 'fees' => '0.00', 'total' => '220.00', 'pricing_snapshot' => ['total' => '220.00'], 'status' => 'confirmed'];
    }
}
