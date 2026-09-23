<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => $code.' Currency',
            'symbol' => $code,
            'decimal_digits' => 2,
            'symbol_position' => Currency::SYMBOL_BEFORE,
            'is_active' => true,
            'is_default_display' => false,
            'sort_order' => fake()->numberBetween(0, 999),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function zeroDecimals(): static
    {
        return $this->state(fn (array $attributes): array => ['decimal_digits' => 0]);
    }
}
