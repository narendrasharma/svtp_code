<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\TourPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TourPackage>
 */
class TourPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(4, true)).' Tour';
        $days = fake()->numberBetween(1, 6);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'city_id' => City::factory(),
            'duration_days' => $days,
            'duration_nights' => max(0, $days - 1),
            'price' => fake()->numberBetween(2000, 25000),
            'overview' => fake()->paragraph(),
            'inclusions' => ['Private AC vehicle', 'Local assistance'],
            'exclusions' => ['Meals', 'Monument entry fees'],
            'category' => \App\Models\TourCategory::factory(),
            'is_featured' => false,
            'is_active' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
