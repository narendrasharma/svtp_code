<?php

namespace Database\Factories;

use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutMethod;
use App\Models\VendorPayoutAccount;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorPayoutAccount>
 */
class VendorPayoutAccountFactory extends Factory
{
    protected $model = VendorPayoutAccount::class;

    public function definition(): array
    {
        return [
            'vendor_profile_id' => VendorProfile::factory(),
            'method' => PayoutMethod::Bank->value,
            'account_holder_name' => fake()->name(),
            'bank_name' => 'Test Bank',
            'account_number' => '123456789012',
            'account_number_last4' => '9012',
            'ifsc' => 'TEST0123456',
            'upi_id' => null,
            'upi_id_masked' => null,
            'status' => PayoutAccountStatus::Pending->value,
            'verified_at' => null,
            'verified_by' => null,
            'rejection_reason' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $a) => ['status' => PayoutAccountStatus::Verified->value, 'verified_at' => now()]);
    }

    public function upi(): static
    {
        return $this->state(fn (array $a) => [
            'method' => PayoutMethod::Upi->value,
            'bank_name' => null,
            'account_number' => null,
            'account_number_last4' => null,
            'ifsc' => null,
            'upi_id' => 'vendorname@upi',
            'upi_id_masked' => 've***@upi',
        ]);
    }
}
