<?php

namespace Database\Factories;

use App\Enums\RoomTypeStatus;
use App\Models\HotelRoomType;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelRoomType>
 */
class HotelRoomTypeFactory extends Factory
{
    protected $model = HotelRoomType::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');

        return [
            'property_id' => Property::factory(),
            'name' => 'Deluxe Test Room '.$suffix,
            'slug' => 'deluxe-test-room-'.$suffix,
            'short_description' => 'A comfortable test room.',
            'max_adults' => 2,
            'max_children' => 1,
            'max_occupancy' => 3,
            'size_value' => 28,
            'size_unit' => 'sqm',
            'inventory_mode' => HotelRoomType::INVENTORY_AGGREGATE,
            'total_units' => 5,
            'status' => RoomTypeStatus::Draft->value,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }
}
