<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<State>
 */
class StateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Unbounded unique suffix instead of faker's 50-row US-state pool,
     * so factories scale. Every state belongs to a country by default —
     * pass ['country_id' => null] explicitly for legacy-style rows.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');
        $name = 'Test State '.$suffix;

        return [
            'country_id' => Country::factory(),
            'name' => $name,
            'code' => 'TS'.$suffix,
            'slug' => Str::slug($name).'-'.$suffix,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 999),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
