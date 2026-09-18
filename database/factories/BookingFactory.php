<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\BookingReference;
use App\Services\MarketplaceCommissionService;
use App\Services\TourBookingPricingService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_type' => 'tour',
            'source' => BookingSource::Website->value,
            'user_id' => User::factory(),
            'package_id' => TourPackage::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('##########'),
            'customer_email' => fake()->safeEmail(),
            'travel_date' => fake()->dateTimeBetween('+1 day', '+90 days')->format('Y-m-d'),
            'total_adults' => fake()->numberBetween(1, 4),
            'total_children' => fake()->numberBetween(0, 2),
            'booking_status' => BookingStatus::Pending->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'booking_reference_id' => BookingReference::generate(),
            'qr_code_string' => Str::uuid()->toString(),
        ];
    }

    /**
     * Fill the immutable price snapshot from the linked tour package so
     * factory rows always carry coherent server-side pricing — plus the
     * historical vendor/commission snapshot BookingService would store.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Booking $booking): void {
            $quote = app(TourBookingPricingService::class)->quote(
                $booking->package,
                (int) $booking->total_adults,
                (int) $booking->total_children
            );

            $booking->forceFill([
                'currency' => $quote['currency'],
                'base_price' => $quote['base_price'],
                'subtotal' => $quote['subtotal'],
                'discount_amount' => $quote['discount_amount'],
                'tax_amount' => $quote['tax_amount'],
                'total_amount' => $quote['total_amount'],
            ]);

            if ($booking->package) {
                $snapshot = app(MarketplaceCommissionService::class)->snapshotForTour(
                    $booking->package,
                    $quote['total_amount']
                );

                $booking->forceFill([
                    'vendor_profile_id' => $snapshot['vendor_profile_id'],
                    'gross_amount' => $snapshot['gross_amount'],
                    'platform_commission_percentage' => $snapshot['platform_commission_percentage'],
                    'platform_commission_amount' => $snapshot['platform_commission_amount'],
                    'vendor_earning_amount' => $snapshot['vendor_earning_amount'],
                ]);
            }
        });
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => null]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => ['booking_status' => BookingStatus::Confirmed->value]);
    }
}
