<?php

namespace Database\Factories;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverEmploymentStatus;
use App\Models\Driver;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'reference' => 'DRV-'.fake()->unique()->numerify('######'),
            'vendor_profile_id' => VendorProfile::factory(),
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->unique()->numerify('9#########'),
            'availability_status' => DriverAvailabilityStatus::Available->value,
            'employment_status' => DriverEmploymentStatus::Active->value,
            'is_active' => true,
        ];
    }
}
