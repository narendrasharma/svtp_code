<?php

namespace Database\Factories;

use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSection>
 */
class HomepageSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_key' => fake()->unique()->slug(2),
            'is_active' => true,
            'sort_order' => 0,
            'settings' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
