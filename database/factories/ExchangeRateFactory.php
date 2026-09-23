<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'base_currency_code' => 'USD',
            'quote_currency_code' => strtoupper(fake()->unique()->lexify('???')),
            'rate' => (string) fake()->randomFloat(6, 0.0001, 200),
            'source' => ExchangeRate::SOURCE_MANUAL,
            'fetched_at' => now(),
        ];
    }

    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => ['fetched_at' => now()->subDays(10)]);
    }
}
