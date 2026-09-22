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
     * Unbounded unique suffix instead of tiny word pools, so factories
     * scale. Every place belongs to a destination by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');
        $name = 'Test Place '.$suffix;

        return [
            'destination_id' => Destination::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$suffix,
            'description' => fake()->paragraph(),
            'meta_description' => fake()->sentence(12),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 999),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
