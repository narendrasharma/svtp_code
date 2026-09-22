<?php

namespace Database\Factories;

use App\Models\HotelRoomType;
use App\Models\HotelRoomUnit;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelRoomUnit>
 */
class HotelRoomUnitFactory extends Factory
{
    protected $model = HotelRoomUnit::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_type_id' => HotelRoomType::factory(),
            'unit_name' => 'Room '.fake()->unique()->numerify('####'),
            'status' => HotelRoomUnit::STATUS_ACTIVE,
        ];
    }

    public function configure(): static
    {
        // Keep the unit's property aligned with its room type's property
        // unless the caller explicitly overrides property_id afterwards.
        return $this->afterMaking(function (HotelRoomUnit $unit): void {
            $roomType = $unit->room_type_id ? HotelRoomType::find($unit->room_type_id) : null;

            if ($roomType && (int) $unit->property_id !== (int) $roomType->property_id) {
                $unit->property_id = $roomType->property_id;
            }
        });
    }
}
