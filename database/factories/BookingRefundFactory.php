<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\BookingRefund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingRefund>
 */
class BookingRefundFactory extends Factory
{
    protected $model = BookingRefund::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'amount' => '2000.00',
            'currency' => 'INR',
            'status' => RefundStatus::Processed->value,
            'reason' => 'Customer requested partial refund',
            'processed_by' => null,
            'processed_at' => now(),
            'reference' => 'booking-refund-'.Str::uuid()->toString(),
            'external_reference' => null,
            'vendor_reversal_amount' => '0.00',
        ];
    }
}
