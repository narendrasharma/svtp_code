<?php

namespace Database\Factories;

use App\Models\HotelDailyRate;
use App\Models\HotelRatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelDailyRate>
 */
class HotelDailyRateFactory extends Factory
{
    protected $model = HotelDailyRate::class;

    public function definition(): array
    {
        // Wide 2-year date pool: explicit dates in tests, random here.
        $date = fake()->dateTimeBetween('+1 days', '+730 days')->format('Y-m-d');

        return [
            'hotel_rate_plan_id' => HotelRatePlan::factory(),
            'rate_date' => $date,
            'amount_override' => null,
            'extra_adult_override' => null,
            'extra_child_override' => null,
            'minimum_stay_override' => null,
            'maximum_stay_override' => null,
            'stop_sell' => false,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'note' => null,
            'source' => HotelDailyRate::SOURCE_MANUAL,
        ];
    }
}
