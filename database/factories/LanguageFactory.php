<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        $code = 'l'.fake()->unique()->lexify('????');

        return [
            'code' => strtolower($code),
            'locale' => strtolower($code),
            'name' => ucfirst(strtolower($code)).' Language',
            'native_name' => ucfirst(strtolower($code)).' Language',
            'is_active' => true,
            'is_default' => false,
            'is_rtl' => false,
            'sort_order' => fake()->numberBetween(0, 999),
            'date_format' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function rtl(): static
    {
        return $this->state(fn (array $attributes): array => ['is_rtl' => true]);
    }
}
