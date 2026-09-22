<?php

namespace Database\Factories;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use App\Models\HotelBooking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HotelBooking> */
class HotelBookingFactory extends Factory
{
    protected $model = HotelBooking::class;

    public function definition(): array
    {
        $in = now()->addDays(30)->toDateString();
        $out = now()->addDays(32)->toDateString();

        return ['booking_number' => 'HT-'.now()->format('Y').'-'.fake()->unique()->numerify('######'), 'user_id' => User::factory(), 'property_id' => Property::factory(), 'property_name_snapshot' => 'Test Hotel', 'status' => HotelBookingStatus::Confirmed->value, 'payment_status' => HotelPaymentStatus::Unpaid->value, 'currency' => 'USD', 'check_in' => $in, 'check_out' => $out, 'nights' => 2, 'rooms_count' => 1, 'adults' => 2, 'children' => 0, 'guest_name' => fake()->name(), 'guest_email' => fake()->safeEmail(), 'guest_phone' => fake()->phoneNumber(), 'subtotal' => '200.00', 'taxes' => '20.00', 'fees' => '0.00', 'total' => '220.00', 'pricing_snapshot' => ['total' => '220.00']];
    }
}
