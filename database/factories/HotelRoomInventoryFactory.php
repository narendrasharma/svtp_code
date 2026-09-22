<?php

namespace Database\Factories;

use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelRoomInventory>
 */
class HotelRoomInventoryFactory extends Factory
{
    protected $model = HotelRoomInventory::class;

    public function definition(): array
    {
        // Wide 2-year date pool: explicit dates in tests, random here.
        $date = fake()->dateTimeBetween('+1 days', '+730 days')->format('Y-m-d');

        return [
            'hotel_room_type_id' => HotelRoomType::factory(),
            'inventory_date' => $date,
            'capacity_override' => null,
            'blocked_units' => 0,
            'stop_sell' => false,
            'note' => null,
            'source' => HotelRoomInventory::SOURCE_MANUAL,
        ];
    }
}
