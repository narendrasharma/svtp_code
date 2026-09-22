<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Unbounded unique suffix instead of faker's small city pool, so
     * factories scale. Relationally valid by default: the city inherits
     * its country's context from its state (configure hook below).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');
        $name = 'Test City '.$suffix;

        return [
            'country_id' => null,
            'state_id' => State::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$suffix,
            'is_spiritual_hub' => false,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(0, 999),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (City $city): void {
            // Relationally valid by default: inherit the country from the
            // state chain. An explicitly provided country_id is left
            // untouched so tests can build mismatch cases on purpose.
            if ($city->country_id !== null) {
                return;
            }

            $city->loadMissing('state');

            if ($city->state && $city->state->country_id !== null) {
                $city->forceFill(['country_id' => $city->state->country_id])->save();
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

    public function withoutState(): static
    {
        return $this->state(fn (array $attributes): array => ['state_id' => null]);
    }
}
