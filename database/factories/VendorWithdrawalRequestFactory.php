<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\VendorProfile;
use App\Models\VendorWithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorWithdrawalRequest>
 */
class VendorWithdrawalRequestFactory extends Factory
{
    protected $model = VendorWithdrawalRequest::class;

    public function definition(): array
    {
        return [
            'vendor_profile_id' => VendorProfile::factory(),
            'amount' => '5000.00',
            'currency' => 'INR',
            'status' => WithdrawalStatus::Pending->value,
            'requested_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'admin_note' => null,
            'rejection_reason' => null,
            'paid_at' => null,
            'payout_reference' => null,
            'vendor_note' => null,
        ];
    }
}
