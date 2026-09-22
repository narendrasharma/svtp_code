<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');

        return [
            'property_type_id' => PropertyType::factory(),
            'name' => 'Harbour View Suites '.$suffix,
            'slug' => 'harbour-view-suites-'.$suffix,
            'short_description' => 'A comfortable demo stay near the waterfront.',
            'description' => '<p>Bright rooms, friendly staff and a quiet courtyard.</p>',
            'status' => PropertyStatus::Draft->value,
            'is_featured' => false,
            'star_rating' => 4,
            'address_line_1' => fake()->streetAddress(),
            'country_code' => 'US',
            'postal_code' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'currency' => 'USD',
        ];
    }
}
