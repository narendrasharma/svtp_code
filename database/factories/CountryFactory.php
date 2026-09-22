<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Unbounded unique suffix (no tiny faker pool): names, ISO codes and
     * slugs never collide no matter how many rows a test creates.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');
        $iso2 = strtoupper(fake()->unique()->lexify('??'));
        $iso3 = strtoupper(fake()->unique()->lexify('???'));
        $name = 'Test Country '.$suffix;

        return [
            'name' => $name,
            'iso2' => $iso2,
            'iso3' => $iso3,
            'phone_code' => '+'.fake()->numerify('##'),
            'currency_code' => strtoupper(fake()->lexify('???')),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 999),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
