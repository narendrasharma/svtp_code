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
     * Unbounded unique suffix instead of faker's small city pool, so
     * factories scale. Relationally valid by default: geography context
     * is inherited from the city chain (configure hook below).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');
        $name = 'Test Destination '.$suffix;

        return [
            'country_id' => null,
            'state_id' => null,
            'city_id' => City::factory(),
            'parent_id' => null,
            'destination_type' => Destination::TYPE_CITY,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$suffix,
            'description' => fake()->paragraph(),
            'meta_description' => fake()->sentence(12),
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(0, 999),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Destination $destination): void {
            // Inherit geography from the city chain unless the test set
            // explicit values (mismatch cases are built on purpose).
            $destination->loadMissing('city');

            $patch = [];

            if ($destination->country_id === null && $destination->city?->country_id !== null) {
                $patch['country_id'] = $destination->city->country_id;
            }

            if ($destination->state_id === null && $destination->city?->state_id !== null) {
                $patch['state_id'] = $destination->city->state_id;
            }

            if ($patch !== []) {
                $destination->forceFill($patch)->save();
            }
        });
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function withoutCity(): static
    {
        return $this->state(fn (array $attributes): array => ['city_id' => null]);
    }
}
