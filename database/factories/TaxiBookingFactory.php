<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\PaymentStatus;
use App\Enums\TaxiBookingStatus;
use App\Enums\TripType;
use App\Models\TaxiBooking;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxiBooking>
 */
class TaxiBookingFactory extends Factory
{
    protected $model = TaxiBooking::class;

    public function definition(): array
    {
        return [
            'reference' => 'TX-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'trip_type' => TripType::OneWay->value,
            'pickup_at' => now()->addDay(),
            'pickup_address' => 'Mathura Junction, '.fake()->streetAddress(),
            'drop_address' => 'Prem Mandir, Vrindavan',
            'passenger_count' => 3,
            'vehicle_type_id' => VehicleType::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('9#########'),
            'source' => BookingSource::Admin->value,
            'status' => TaxiBookingStatus::Confirmed->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'currency' => 'INR',
            'total_amount' => 2500,
            'base_amount' => 2500,
        ];
    }
}
