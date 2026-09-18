<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPayment>
 */
class BookingPaymentFactory extends Factory
{
    protected $model = BookingPayment::class;

    public function definition(): array
    {
        return [
            'reference' => 'PAY-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'booking_id' => Booking::factory(),
            'amount' => 1000,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'paid_at' => now(),
        ];
    }
}
