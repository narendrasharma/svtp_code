<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VendorPlan;
use App\Models\VendorPlanAssignment;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    protected $model = VendorProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'entity_type' => 'individual',
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country_code' => 'IN',
            'postcode' => fake()->postcode(),
            'website' => null,
            'business_description' => null,
            'is_active' => true,
            'approved_at' => now(),
            'storefront_enabled' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (VendorProfile $profile): void {
            if ($profile->slug === null) {
                $profile->update([
                    'slug' => Str::slug($profile->business_name ?: 'vendor').'-'.$profile->id,
                ]);
            }

            // Phase 11: factory vendors get the default plan when one
            // exists (mirrors approval flow; keeps tests realistic).
            if ($profile->vendor_plan_id === null) {
                $default = VendorPlan::where('is_default', true)->where('is_active', true)->first();

                if ($default) {
                    $profile->update(['vendor_plan_id' => $default->id]);
                    VendorPlanAssignment::firstOrCreate(
                        ['vendor_profile_id' => $profile->id, 'vendor_plan_id' => $default->id],
                        ['starts_at' => now(), 'note' => 'Factory default plan.']
                    );
                }
            }
        });
    }
}
