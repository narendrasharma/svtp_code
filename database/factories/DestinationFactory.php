<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Destination>
 */
class DestinationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'city_id' => City::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'meta_description' => fake()->sentence(12),
        ];
    }
}
