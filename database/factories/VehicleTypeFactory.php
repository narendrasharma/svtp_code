<?php

namespace Database\Factories;

use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    protected $model = VehicleType::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Sedan', 'SUV', 'MUV', 'Hatchback', 'Luxury', 'Traveller']).' '.fake()->unique()->numerify('###');

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'passenger_capacity' => 4,
            'luggage_capacity' => 2,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
