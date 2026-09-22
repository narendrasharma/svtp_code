<?php

namespace Database\Factories;

use App\Models\HotelCustomFieldDefinition;
use App\Models\HotelCustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelCustomFieldValue>
 */
class HotelCustomFieldValueFactory extends Factory
{
    protected $model = HotelCustomFieldValue::class;

    public function definition(): array
    {
        return [
            'definition_id' => HotelCustomFieldDefinition::factory(),
            'entity_type' => HotelCustomFieldDefinition::ENTITY_PROPERTY,
            'entity_id' => fake()->numberBetween(1, 999999),
            'value' => 'Demo value '.fake()->numerify('######'),
        ];
    }
}
