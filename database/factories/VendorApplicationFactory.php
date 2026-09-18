<?php

namespace Database\Factories;

use App\Enums\EntityType;
use App\Enums\VendorApplicationStatus;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorApplication>
 */
class VendorApplicationFactory extends Factory
{
    protected $model = VendorApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => VendorApplicationStatus::Pending->value,
            'business_name' => fake()->company(),
            'entity_type' => fake()->randomElement(EntityType::cases())->value,
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country_code' => 'IN',
            'postcode' => fake()->postcode(),
            'website' => fake()->optional()->url(),
            'business_description' => fake()->optional()->sentence(),
            'consent_accepted_at' => now(),
            'consent_policy_version' => 'v1',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorApplicationStatus::Approved->value]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorApplicationStatus::Rejected->value]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorApplicationStatus::Pending->value]);
    }

    public function resubmissionRequested(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorApplicationStatus::ResubmissionRequested->value]);
    }
}
