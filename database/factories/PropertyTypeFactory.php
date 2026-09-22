<?php

namespace Database\Factories;

use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyType>
 */
class PropertyTypeFactory extends Factory
{
    protected $model = PropertyType::class;

    public function definition(): array
    {
        // Sequence-free unique suffix: 6-digit pool, never the tiny
        // fixed-list exhaustion seen elsewhere.
        $suffix = fake()->unique()->numerify('######');

        return [
            'name' => 'Lodge Type '.$suffix,
            'slug' => 'lodge-type-'.$suffix,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
