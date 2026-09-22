<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\TaxiReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CodeCanyon-safe Taxi demo data (Phase 12A.13).
 *
 * Explicitly invoked only — NEVER called from DatabaseSeeder:
 *
 *   php artisan db:seed --class=TaxiDemoSeeder
 *
 * Idempotent: every record uses fixed demo keys and firstOrCreate, so
 * re-running never duplicates or touches real data. All identities use
 * clearly-fake example.com addresses; no external images.
 */
class TaxiDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $vendor = $this->vendor();
            $customer = $this->customer();
            $type = $this->vehicleType();
            [$driverA, $driverB, $vehicleA, $vehicleB] = $this->fleet($vendor, $type);

            $this->booking('TX-DEMO-PENDING', $vendor, $customer, $type, [
                'status' => TaxiBookingStatus::Confirmed->value,
                'pickup_at' => now()->addDay(),
            ]);

            $assigned = $this->booking('TX-DEMO-ACTIVE', $vendor, $customer, $type, [
                'status' => TaxiBookingStatus::Confirmed->value,
                'pickup_at' => now()->addHours(5),
            ]);

            if ($assigned->assignments()->open()->where('driver_id', $driverA->id)->doesntExist()) {
                app(TaxiBookingService::class)->assign($assigned->fresh(), $driverA, $vehicleA);
            }

            $completed = $this->booking('TX-DEMO-DONE', $vendor, $customer, $type, [
                'status' => TaxiBookingStatus::Completed->value,
                'pickup_at' => now()->subDays(2),
                'completed_at' => now()->subDays(2),
                'payment_status' => PaymentStatus::Paid->value,
            ]);

            if ($completed->assignments()->where('driver_id', $driverB->id)->doesntExist()) {
                $completed->assignments()->create([
                    'driver_id' => $driverB->id,
                    'vehicle_id' => $vehicleB->id,
                    'assigned_at' => now()->subDays(2),
                    'unassigned_at' => now()->subDays(2),
                ]);
                $completed->forceFill([
                    'assigned_driver_id' => $driverB->id,
                    'assigned_vehicle_id' => $vehicleB->id,
                ])->save();
            }

            TaxiReview::firstOrCreate(
                ['taxi_booking_id' => $completed->id],
                [
                    'customer_user_id' => $customer->id,
                    'vendor_profile_id' => $vendor->id,
                    'driver_id' => $driverB->id,
                    'vehicle_id' => $vehicleB->id,
                    'overall_rating' => 5,
                    'driver_rating' => 5,
                    'service_rating' => 5,
                    'comment' => 'Demo review: smooth airport transfer, on time.',
                    'status' => TaxiReview::STATUS_APPROVED,
                    'submitted_at' => now()->subDays(2),
                    'approved_at' => now()->subDays(2),
                ]
            );
        });

        $this->command->info('Taxi demo data ready (TX-DEMO-PENDING / TX-DEMO-ACTIVE / TX-DEMO-DONE).');
    }

    protected function vendor(): VendorProfile
    {
        $user = User::firstOrCreate(
            ['email' => 'demo-cabs@example.com'],
            ['name' => 'Demo Cabs Owner', 'password' => Hash::make('password'), 'role' => 'vendor']
        );

        return VendorProfile::firstOrCreate(
            ['slug' => 'demo-cabs'],
            [
                'user_id' => $user->id,
                'business_name' => 'Demo Cabs',
                'entity_type' => 'company',
                'phone' => '9000000001',
                'email' => 'demo-cabs@example.com',
                'city' => 'Demo City',
                'is_active' => true,
            ]
        );
    }

    protected function customer(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo-traveller@example.com'],
            ['name' => 'Demo Traveller', 'password' => Hash::make('password'), 'role' => 'customer']
        );
    }

    protected function vehicleType(): VehicleType
    {
        return VehicleType::firstOrCreate(
            ['slug' => 'demo-sedan'],
            ['name' => 'Demo Sedan', 'passenger_capacity' => 4, 'luggage_capacity' => 2, 'is_active' => true, 'sort_order' => 0]
        );
    }

    /**
     * @return array{0: Driver, 1: Driver, 2: Vehicle, 3: Vehicle}
     */
    protected function fleet(VendorProfile $vendor, VehicleType $type): array
    {
        $makeDriver = function (string $reference, string $first, string $last) use ($vendor): Driver {
            $user = User::firstOrCreate(
                ['email' => $reference.'@example.com'],
                ['name' => $first.' '.$last, 'password' => Hash::make('password'), 'role' => 'customer']
            );

            return Driver::firstOrCreate(
                ['reference' => $reference],
                [
                    'vendor_profile_id' => $vendor->id,
                    'user_id' => $user->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => '9000000002',
                    'availability_status' => 'available',
                    'employment_status' => 'active',
                    'is_active' => true,
                ]
            );
        };

        $makeVehicle = function (string $reference, string $registration) use ($vendor, $type): Vehicle {
            return Vehicle::firstOrCreate(
                ['reference' => $reference],
                [
                    'vendor_profile_id' => $vendor->id,
                    'vehicle_type_id' => $type->id,
                    'name' => 'Demo Sedan ('.$registration.')',
                    'registration_number' => $registration,
                    'passenger_capacity' => 4,
                    'status' => 'available',
                    'is_active' => true,
                ]
            );
        };

        $driverA = $makeDriver('DRV-DEMO-01', 'Demo', 'DriverA');
        $driverB = $makeDriver('DRV-DEMO-02', 'Demo', 'DriverB');

        return [$driverA, $driverB, $makeVehicle('VEH-DEMO-01', 'DEMO0001'), $makeVehicle('VEH-DEMO-02', 'DEMO0002')];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function booking(string $reference, VendorProfile $vendor, User $customer, VehicleType $type, array $overrides): TaxiBooking
    {
        $existing = TaxiBooking::where('reference', $reference)->first();

        if ($existing) {
            return $existing;
        }

        $booking = TaxiBooking::create([
            'reference' => $reference,
            'customer_user_id' => $customer->id,
            'vendor_profile_id' => $vendor->id,
            'trip_type' => 'one_way',
            'pickup_at' => now()->addDay(),
            'pickup_address' => 'Demo Pickup Point, Demo City',
            'drop_address' => 'Demo Airport Terminal 1',
            'passenger_count' => 2,
            'vehicle_type_id' => $type->id,
            'customer_name' => $customer->name,
            'customer_phone' => '9000000003',
            'customer_email' => $customer->email,
            'source' => 'admin',
            'payment_status' => PaymentStatus::Unpaid->value,
        ]);

        // Money columns are server-owned (not fillable) — set explicitly.
        $booking->forceFill([
            'currency' => 'INR',
            'total_amount' => '1200.00',
            'base_amount' => '1200.00',
            'status' => $overrides['status'] ?? TaxiBookingStatus::Confirmed->value,
            'pickup_at' => $overrides['pickup_at'] ?? now()->addDay(),
            'completed_at' => $overrides['completed_at'] ?? null,
            'payment_status' => $overrides['payment_status'] ?? PaymentStatus::Unpaid->value,
        ])->save();

        return $booking->fresh();
    }
}
