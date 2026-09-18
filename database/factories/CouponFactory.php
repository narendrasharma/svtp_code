<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('SAVE????')),
            'name' => fake()->words(3, true),
            'description' => null,
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'minimum_booking_amount' => null,
            'maximum_discount_amount' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'scope' => Coupon::SCOPE_GLOBAL,
            'vendor_profile_id' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    public function fixed(float $value = 500): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => $value,
        ]);
    }

    public function percentage(float $value = 10): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => $value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(10),
        ]);
    }

    public function forVendor(VendorProfile $profile, ?User $creator = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => Coupon::SCOPE_VENDOR,
            'vendor_profile_id' => $profile->id,
            'created_by' => $creator?->id,
        ]);
    }
}
