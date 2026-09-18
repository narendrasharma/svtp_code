<?php

namespace Database\Factories;

use App\Enums\VendorVerificationStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorVerification>
 */
class VendorVerificationFactory extends Factory
{
    protected $model = VendorVerification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vendor_application_id' => VendorApplication::factory(),
            'status' => VendorVerificationStatus::Pending->value,
            'country_code' => 'IN',
            'entity_type' => 'individual',
            'submitted_at' => now(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorVerificationStatus::Verified->value, 'verified_at' => now()]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorVerificationStatus::Pending->value]);
    }
}
