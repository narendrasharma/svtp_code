<?php

namespace Database\Factories;

use App\Models\HotelRatePlan;
use App\Models\HotelRateSeason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelRateSeason>
 */
class HotelRateSeasonFactory extends Factory
{
    protected $model = HotelRateSeason::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');
        $start = fake()->dateTimeBetween('+1 days', '+300 days');

        return [
            'hotel_rate_plan_id' => HotelRatePlan::factory(),
            'name' => 'Season '.$suffix,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+14 days')->format('Y-m-d'),
            'adjustment_type' => HotelRateSeason::ADJUST_PERCENTAGE,
            'adjustment_value' => '10.00',
            'priority' => 0,
            'applicable_weekdays' => null,
            'is_active' => true,
        ];
    }
}
