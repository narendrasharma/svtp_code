<?php

namespace Database\Factories;

use App\Models\TourAddon;
use App\Models\TourPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourAddon>
 */
class TourAddonFactory extends Factory
{
    protected $model = TourAddon::class;

    public function definition(): array
    {
        return [
            'tour_package_id' => TourPackage::factory(),
            'name' => fake()->words(2, true),
            'description' => null,
            'pricing_type' => TourAddon::PRICING_FIXED,
            'price' => fake()->numberBetween(100, 2000),
            'is_required' => false,
            'is_active' => true,
            'max_quantity' => null,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes): array => ['is_required' => true]);
    }
}
