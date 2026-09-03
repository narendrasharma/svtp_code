<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'destination_id' => Destination::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'meta_description' => fake()->sentence(12),
        ];
    }
}
