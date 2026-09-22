<?php

namespace Database\Factories;

use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelRatePlan>
 */
class HotelRatePlanFactory extends Factory
{
    protected $model = HotelRatePlan::class;

    public function definition(): array
    {
        // Sequence-free unique suffix: 6-digit pool, never a tiny list.
        $suffix = fake()->unique()->numerify('######');

        return [
            'property_id' => Property::factory(),
            'hotel_room_type_id' => HotelRoomType::factory(),
            'name' => 'Flexible Room Only '.$suffix,
            'code' => 'flex-room-only-'.$suffix,
            'currency' => 'USD',
            'meal_plan' => HotelRatePlan::MEAL_ROOM_ONLY,
            'cancellation_mode' => HotelRatePlan::CANCEL_FLEXIBLE,
            'base_adults' => 2,
            'base_children' => 0,
            'base_rate' => '120.00',
            'extra_adult_rate' => '30.00',
            'extra_child_rate' => '15.00',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        // Keep the plan's property aligned with its room type's property
        // unless the caller explicitly overrides property_id afterwards.
        return $this->afterMaking(function (HotelRatePlan $plan): void {
            $roomType = $plan->hotel_room_type_id ? HotelRoomType::find($plan->hotel_room_type_id) : null;

            if ($roomType && (int) $plan->property_id !== (int) $roomType->property_id) {
                $plan->property_id = $roomType->property_id;
            }
        });
    }
}
