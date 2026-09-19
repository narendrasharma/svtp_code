<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

        return $vendor->fresh();
    }

    /**
     * @return array{0: Driver, 1: Vehicle}
     */
    protected function fleetFor(VendorProfile $profile, array $driverOverrides = [], array $vehicleOverrides = []): array
    {
        $driver = Driver::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ], $driverOverrides));

        $vehicle = Vehicle::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'status' => 'available',
            'is_active' => true,
            'passenger_capacity' => 4,
        ], $vehicleOverrides));

        return [$driver, $vehicle];
    }

    protected function bookingFor(VendorProfile $profile, array $overrides = []): TaxiBooking
    {
        return TaxiBooking::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'status' => TaxiBookingStatus::Confirmed->value,
            'pickup_at' => now()->addHours(5),
            'passenger_count' => 2,
        ], $overrides));
    }

    public function test_admin_dispatch_board_renders(): void
    {
        $vendor = VendorProfile::factory()->create();
        $this->bookingFor($vendor);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.dispatch.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Taxi/Dispatch/Index')
                ->has('board.data', 1)
                ->has('metrics')
                ->has('bucketCounts')
                ->has('buckets')
                ->has('timeViews'));
    }

    public function test_vendor_dispatch_board_renders(): void
    {
        $vendor = $this->makeVendor();
        $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($vendor)
            ->get(route('vendor.taxi.dispatch.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vendor/Taxi/Dispatch/Index')
                ->has('board.data', 1)
                ->has('metrics'));
    }

    public function test_vendor_only_sees_own_bookings(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();

        $this->bookingFor($vendorA->vendorProfile, ['reference' => 'TX-DISP-A-001']);
        $this->bookingFor($vendorB->vendorProfile, ['reference' => 'TX-DISP-B-001']);

        $this->actingAs($vendorA)
            ->get(route('vendor.taxi.dispatch.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vendor/Taxi/Dispatch/Index')
                ->has('board.data', 1)
                ->where('board.data.0.reference', 'TX-DISP-A-001'));
    }

    public function test_eligible_driver_vehicle_assignment(): void
    {
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame($driver->id, $booking->assigned_driver_id);
        $this->assertSame(TaxiBookingStatus::DriverAssigned->value, $booking->status);
        $this->assertDatabaseHas('taxi_assignments', [
            'taxi_booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_cross_vendor_assignment_rejected(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        [$driverA] = $this->fleetFor($vendorA->vendorProfile);
        [, $vehicleB] = $this->fleetFor($vendorB->vendorProfile);
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $driverA->id,
                'vehicle_id' => $vehicleB->id,
            ])
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_inactive_driver_rejected(): void
    {
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile, ['is_active' => false]);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('driver_id');
    }

    public function test_unavailable_and_on_leave_driver_rejected(): void
    {
        $vendor = $this->makeVendor();
        $pickup = now()->addHours(5);

        [$busyDriver, $vehicle] = $this->fleetFor($vendor->vendorProfile, ['availability_status' => 'offline']);
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => $pickup]);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $busyDriver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('driver_id');

        [$leaveDriver, $vehicle2] = $this->fleetFor($vendor->vendorProfile);
        $leaveDriver->availabilities()->create([
            'from_at' => $pickup->copy()->subDay(),
            'to_at' => $pickup->copy()->addDay(),
            'status' => 'on_leave',
        ]);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $leaveDriver->id,
                'vehicle_id' => $vehicle2->id,
            ])
            ->assertSessionHasErrors('driver_id');
    }

    public function test_maintenance_vehicle_rejected(): void
    {
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile, [], ['status' => 'maintenance']);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_insufficient_capacity_rejected(): void
    {
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile, [], ['passenger_capacity' => 2]);
        $booking = $this->bookingFor($vendor->vendorProfile, ['passenger_count' => 5]);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_overlapping_trip_rejected(): void
    {
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);

        $first = $this->bookingFor($vendor->vendorProfile);
        $this->actingAs($this->makeAdmin())->post(route('admin.taxi.bookings.assign', $first, absolute: false), [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertRedirect();

        $this->assertSame(TaxiBookingStatus::DriverAssigned->value, $first->fresh()->status);

        $second = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $second, absolute: false), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('assignment');
    }

    public function test_reassignment_preserves_history(): void
    {
        $vendor = $this->makeVendor();
        [$driver1, $vehicle1] = $this->fleetFor($vendor->vendorProfile);
        [$driver2, $vehicle2] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
            'driver_id' => $driver1->id,
            'vehicle_id' => $vehicle1->id,
            'note' => 'First assignment',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
            'driver_id' => $driver2->id,
            'vehicle_id' => $vehicle2->id,
            'note' => 'Driver changed',
        ])->assertRedirect();

        $history = TaxiAssignment::where('taxi_booking_id', $booking->id)->orderBy('assigned_at')->get();

        $this->assertCount(2, $history);
        $this->assertNotNull($history->first()->unassigned_at);
        $this->assertNull($history->last()->unassigned_at);
        $this->assertSame($driver2->id, $booking->fresh()->assigned_driver_id);
        $this->assertSame('Driver changed', $history->last()->note);
    }

    public function test_status_transitions_valid(): void
    {
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertRedirect();

        foreach ([TaxiBookingStatus::EnRoute, TaxiBookingStatus::Arrived, TaxiBookingStatus::PassengerOnBoard, TaxiBookingStatus::Completed] as $next) {
            $this->actingAs($admin)
                ->patch(route('admin.taxi.bookings.status', $booking, absolute: false), ['status' => $next->value])
                ->assertRedirect();
            $this->assertSame($next->value, $booking->fresh()->status);
        }

        $this->assertDatabaseHas('taxi_booking_status_histories', [
            'taxi_booking_id' => $booking->id,
            'to_status' => TaxiBookingStatus::Completed->value,
        ]);
    }

    public function test_invalid_transition_rejected(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.taxi.bookings.status', $booking, absolute: false), ['status' => TaxiBookingStatus::Completed->value])
            ->assertSessionHasErrors('status');
    }

    public function test_no_show_and_cancel_handling(): void
    {
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.taxi.bookings.status', $booking, absolute: false), ['status' => TaxiBookingStatus::NoShow->value])
            ->assertRedirect();
        $this->assertSame(TaxiBookingStatus::NoShow->value, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->cancelled_at);

        $booking2 = $this->bookingFor($vendor->vendorProfile);
        $this->actingAs($admin)
            ->patch(route('admin.taxi.bookings.status', $booking2, absolute: false), ['status' => TaxiBookingStatus::Cancelled->value])
            ->assertRedirect();
        $this->assertSame(TaxiBookingStatus::Cancelled->value, $booking2->fresh()->status);
    }

    public function test_admin_filters(): void
    {
        $vendorA = VendorProfile::factory()->create(['business_name' => 'Dispatch Vendor A']);
        $vendorB = VendorProfile::factory()->create(['business_name' => 'Dispatch Vendor B']);

        $this->bookingFor($vendorA, ['reference' => 'TX-FILTER-111', 'status' => TaxiBookingStatus::Confirmed->value]);
        $this->bookingFor($vendorB, ['reference' => 'TX-FILTER-222', 'status' => TaxiBookingStatus::Completed->value, 'completed_at' => now()]);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.taxi.dispatch.index', ['search' => 'TX-FILTER-111'], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.data', 1)
                ->where('board.data.0.reference', 'TX-FILTER-111'));

        $this->actingAs($admin)
            ->get(route('admin.taxi.dispatch.index', ['vendor_id' => $vendorB->id], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.data', 1)
                ->where('board.data.0.reference', 'TX-FILTER-222'));

        $this->actingAs($admin)
            ->get(route('admin.taxi.dispatch.index', ['bucket' => 'unassigned'], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.data', 1)
                ->where('board.data.0.reference', 'TX-FILTER-111'));
    }

    public function test_vendor_isolation(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)
            ->get(route('vendor.taxi.dispatch.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('board.data', 0));

        $this->actingAs($vendorB)
            ->get(route('vendor.taxi.dispatch.eligible', $booking, absolute: false))
            ->assertNotFound();

        $this->actingAs($vendorB)
            ->post(route('vendor.taxi.dispatch.notes.store', $booking, absolute: false), ['body' => 'Sneaky note'])
            ->assertNotFound();

        $this->actingAs($vendorB)
            ->get(route('vendor.taxi.bookings.show', $booking, absolute: false))
            ->assertNotFound();
    }

    public function test_internal_operational_note_visibility(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.dispatch.notes.store', $booking, absolute: false), ['body' => 'Customer contacted, gate 2'])
            ->assertRedirect();

        $this->assertDatabaseHas('taxi_booking_notes', [
            'taxi_booking_id' => $booking->id,
            'body' => 'Customer contacted, gate 2',
        ]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.dispatch.notes.index', $booking, absolute: false))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Customer contacted, gate 2']);

        $this->actingAs($vendor)
            ->post(route('vendor.taxi.dispatch.notes.store', $booking, absolute: false), ['body' => 'Vehicle delayed 10 min'])
            ->assertRedirect();

        $this->actingAs($vendor)
            ->get(route('vendor.taxi.dispatch.notes.index', $booking, absolute: false))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Vehicle delayed 10 min']);
    }

    public function test_module_disabled_blocks_dispatch(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.dispatch.index', absolute: false))
            ->assertNotFound();

        $this->actingAs($this->makeVendor())
            ->get(route('vendor.taxi.dispatch.index', absolute: false))
            ->assertNotFound();
    }

    public function test_audit_and_notifications_on_dispatch(): void
    {
        Notification::fake();

        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $driver->update(['user_id' => User::factory()->create()->id]);
        $driver->refresh();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('taxi_booking_status_histories', [
            'taxi_booking_id' => $booking->id,
            'to_status' => TaxiBookingStatus::DriverAssigned->value,
        ]);

        Notification::assertSentTo($driver->user, CrmNotification::class);

        $this->actingAs($admin)
            ->patch(route('admin.taxi.bookings.status', $booking, absolute: false), ['status' => TaxiBookingStatus::EnRoute->value])
            ->assertRedirect();

        $this->assertDatabaseHas('taxi_booking_status_histories', [
            'taxi_booking_id' => $booking->id,
            'to_status' => TaxiBookingStatus::EnRoute->value,
        ]);
    }
}
