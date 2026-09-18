<?php

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'reference' => 'VEH-'.fake()->unique()->numerify('######'),
            'vendor_profile_id' => VendorProfile::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'name' => fake()->randomElement(['Swift Dzire', 'Innova Crysta', 'Ertiga', 'Xylo']).' Taxi',
            'registration_number' => 'UP'.fake()->unique()->numerify('##').fake()->randomElement(['A', 'B', 'C']).fake()->numerify('####'),
            'passenger_capacity' => 4,
            'status' => VehicleStatus::Available->value,
            'is_active' => true,
        ];
    }
}
