<?php

namespace Database\Factories;

use App\Models\HotelChargeRule;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelChargeRule>
 */
class HotelChargeRuleFactory extends Factory
{
    protected $model = HotelChargeRule::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');

        return [
            'property_id' => Property::factory(),
            'name' => 'Service Tax '.$suffix,
            'charge_type' => HotelChargeRule::TYPE_TAX,
            'calculation' => HotelChargeRule::CALC_PERCENTAGE,
            'value' => '10.00',
            'currency' => 'USD',
            'included_in_price' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
