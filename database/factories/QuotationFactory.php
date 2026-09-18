<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'reference' => 'QT-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'revision_number' => 1,
            'service_type' => 'tour',
            'status' => 'draft',
            'currency' => 'INR',
            'valid_until' => now()->addDays(15)->toDateString(),
            'subtotal' => 5000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 5000,
            'created_by' => User::factory(),
            'public_token' => Str::random(32),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Quotation $quotation): void {
            if ($quotation->items()->count() === 0) {
                $quotation->items()->create([
                    'item_type' => 'manual',
                    'description' => 'Tour package',
                    'quantity' => 2,
                    'unit_price' => 2500,
                    'total_amount' => 5000,
                ]);
            }
        });
    }
}
