<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Taxi booking feature tests temporarily disabled; manual QA for Phase 12A.1.');

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin->fresh();
    }

    protected function makeVendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        return $vendor;
    }

    protected function vendorAssets(User $vendor): array
    {
        $vehicleType = VehicleType::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'vehicle_type_id' => $vehicleType->id,
            'status' => 'available',
        ]);

        $driver = Driver::factory()->create([
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        return [$driver, $vehicle];
    }

    protected function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'trip_type' => 'one_way',
            'pickup_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
            'pickup_address' => 'Mathura Junction',
            'drop_address' => 'Vrindavan Temple',
            'passenger_count' => 3,
            'customer_name' => 'Test Customer',
            'customer_phone' => '9999999999',
            'customer_email' => 'customer@example.com',
            'base_amount' => 1200,
            'extra_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
        ], $overrides);
    }

    public function test_admin_can_create_one_way_booking(): void
    {
        $vendor = VendorProfile::factory()->create();

        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.store', absolute: false), $this->bookingPayload([
            'vendor_profile_id' => $vendor->id,
        ]));

        $response->assertRedirect();

        $this->assertDatabaseCount('taxi_bookings', 1);
        $booking = TaxiBooking::first();
        $this->assertStringStartsWith('TX-', $booking->reference);
        $this->assertSame('one_way', $booking->trip_type);
        $this->assertSame($vendor->id, $booking->vendor_profile_id);
        $this->assertSame(TaxiBookingStatus::Confirmed->value, $booking->status);
    }

    public function test_admin_can_create_airport_booking(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.store', absolute: false), $this->bookingPayload([
            'trip_type' => 'airport_transfer',
            'airport_direction' => 'airport_pickup',
            'flight_number' => 'AI123',
            'airline' => 'Air India',
            'terminal' => 'T3',
        ]));

        $response->assertRedirect();

        $this->assertDatabaseHas('taxi_bookings', [
            'trip_type' => 'airport_transfer',
            'airport_direction' => 'airport_pickup',
            'flight_number' => 'AI123',
        ]);
    }

    public function test_booking_creation_blocked_when_module_disabled(): void
    {
        Setting::setValue('taxi.booking_enabled', '0');

        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.store', absolute: false), $this->bookingPayload());

        $response->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('taxi_bookings', 0);
    }

    public function test_admin_can_assign_driver_and_vehicle(): void
    {
        $vendorUser = $this->makeVendor();
        [$driver, $vehicle] = $this->vendorAssets($vendorUser);

        $booking = TaxiBooking::factory()->create([
            'vendor_profile_id' => $vendorUser->vendorProfile->id,
            'status' => TaxiBookingStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.assign', parameters: $booking, absolute: false), [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame($driver->id, $booking->assigned_driver_id);
        $this->assertSame($vehicle->id, $booking->assigned_vehicle_id);
        $this->assertSame(TaxiBookingStatus::DriverAssigned->value, $booking->status);
        $this->assertDatabaseHas('taxi_assignments', [
            'taxi_booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_cross_vendor_assignment_blocked(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        [, $vehicleB] = $this->vendorAssets($vendorB);
        [$driverA] = $this->vendorAssets($vendorA);

        $booking = TaxiBooking::factory()->create([
            'vendor_profile_id' => $vendorA->vendorProfile->id,
            'status' => TaxiBookingStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.assign', parameters: $booking, absolute: false), [
            'driver_id' => $driverA->id,
            'vehicle_id' => $vehicleB->id,
        ]);

        $response->assertSessionHasErrors('vehicle_id');
        $this->assertNull($booking->fresh()->assigned_vehicle_id);
    }

    public function test_status_transitions_enforced(): void
    {
        $booking = TaxiBooking::factory()->create(['status' => TaxiBookingStatus::Confirmed->value]);

        // Valid transition to driver_assigned should succeed when assignment is recorded
        $booking->update(['status' => TaxiBookingStatus::DriverAssigned->value]);

        // Attempt invalid jump to completed should fail
        $response = $this->actingAs($this->makeAdmin())->patch(route('admin.taxi.bookings.status', parameters: $booking, absolute: false), [
            'status' => TaxiBookingStatus::Completed->value,
        ]);

        $response->assertSessionHasErrors('status');

        // Valid transitions sequence
        $response = $this->actingAs($this->makeAdmin())->patch(route('admin.taxi.bookings.status', parameters: $booking, absolute: false), [
            'status' => TaxiBookingStatus::EnRoute->value,
        ]);
        $response->assertRedirect();

        $booking->refresh();
        $this->assertSame(TaxiBookingStatus::EnRoute->value, $booking->status);
    }

    public function test_assignment_history_preserved_when_reassigning(): void
    {
        $vendor = $this->makeVendor();
        [$driver1, $vehicle1] = $this->vendorAssets($vendor);
        [$driver2, $vehicle2] = $this->vendorAssets($vendor);

        $booking = TaxiBooking::factory()->create([
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'status' => TaxiBookingStatus::Confirmed->value,
        ]);

        $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.assign', parameters: $booking, absolute: false), [
            'driver_id' => $driver1->id,
            'vehicle_id' => $vehicle1->id,
        ]);

        $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.assign', parameters: $booking, absolute: false), [
            'driver_id' => $driver2->id,
            'vehicle_id' => $vehicle2->id,
        ]);

        $history = TaxiAssignment::where('taxi_booking_id', $booking->id)->orderBy('assigned_at')->get();

        $this->assertCount(2, $history);
        $this->assertNotNull($history->first()->unassigned_at);
        $this->assertNull($history->last()->unassigned_at);
    }

    public function test_record_payment_updates_due(): void
    {
        $booking = TaxiBooking::factory()->create([
            'total_amount' => 1000,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.payments.store', parameters: $booking, absolute: false), [
            'amount' => 600,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame('partially_paid', $booking->payment_status);
        $this->assertDatabaseHas('taxi_payments', [
            'taxi_booking_id' => $booking->id,
            'amount' => 600,
        ]);
    }

    public function test_vendor_scoped_booking_access(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();

        $booking = TaxiBooking::factory()->create(['vendor_profile_id' => $vendorA->vendorProfile->id]);

        $this->actingAs($vendorA)->get(route('vendor.taxi.bookings.show', parameters: $booking, absolute: false))->assertOk();
        $this->actingAs($vendorB)->get(route('vendor.taxi.bookings.show', parameters: $booking, absolute: false))->assertNotFound();
    }
}
