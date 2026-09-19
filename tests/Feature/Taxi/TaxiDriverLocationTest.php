<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use App\Services\TaxiDriverLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 12A.5 driver location telemetry (provider-neutral, no maps).
 *
 * Run ONE method at a time inside the OS memory cage — never the whole
 * file (see Phase 12A.3 OOM history).
 */
class TaxiDriverLocationTest extends TestCase
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

    protected function makeDriver(VendorProfile $profile, ?User $user = null, array $overrides = []): Driver
    {
        $user ??= User::factory()->create(['role' => 'customer']);

        return Driver::factory()->create(array_merge([
            'user_id' => $user->id,
            'vendor_profile_id' => $profile->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ], $overrides))->fresh();
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

    protected function vehicleFor(VendorProfile $profile): Vehicle
    {
        return Vehicle::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => 'available',
            'is_active' => true,
            'passenger_capacity' => 4,
        ]);
    }

    protected function pingPayload(array $overrides = []): array
    {
        return array_merge([
            'latitude' => 27.1751000,
            'longitude' => 78.0421000,
            'accuracy_meters' => 12,
        ], $overrides);
    }

    public function test_driver_can_submit_own_valid_location(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        app(TaxiBookingService::class)->assign($booking, $driver, $this->vehicleFor($profile));

        $response = $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload());

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('trip_linked', true)
            ->assertJsonPath('freshness', 'live');

        $this->assertDatabaseHas('taxi_driver_locations', [
            'driver_id' => $driver->id,
            'taxi_booking_id' => $booking->id,
        ]);
    }

    public function test_invalid_latitude_rejected(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload(['latitude' => 91]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->assertDatabaseCount('taxi_driver_locations', 0);
    }

    public function test_invalid_longitude_rejected(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload(['longitude' => 200]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->assertDatabaseCount('taxi_driver_locations', 0);
    }

    public function test_unlinked_user_cannot_submit(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertForbidden();
    }

    public function test_inactive_driver_blocked(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile, null, ['is_active' => false]);

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertForbidden();
    }

    public function test_location_associates_to_current_assignment(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        app(TaxiBookingService::class)->assign($booking, $driver, $this->vehicleFor($profile));

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertCreated();

        $ping = TaxiDriverLocation::firstOrFail();
        $this->assertSame($booking->id, $ping->taxi_booking_id);
        $this->assertNotNull($ping->taxi_assignment_id);
        $this->assertSame($driver->id, $ping->driver_id);
    }

    public function test_driver_cannot_forge_booking_id(): void
    {
        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);
        $bookingA = $this->bookingFor($profile);
        $bookingB = $this->bookingFor($profile);
        app(TaxiBookingService::class)->assign($bookingA, $driverA, $this->vehicleFor($profile));
        app(TaxiBookingService::class)->assign($bookingB, $driverB, $this->vehicleFor($profile));

        $this->actingAs($driverA->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload([
                'taxi_booking_id' => $bookingB->id,
                'driver_id' => $driverB->id,
                'vendor_profile_id' => $profile->id,
            ]))
            ->assertCreated();

        $this->assertDatabaseMissing('taxi_driver_locations', [
            'driver_id' => $driverA->id,
            'taxi_booking_id' => $bookingB->id,
        ]);
        $this->assertDatabaseHas('taxi_driver_locations', [
            'driver_id' => $driverA->id,
            'taxi_booking_id' => $bookingA->id,
        ]);
    }

    public function test_driver_cannot_forge_driver_id(): void
    {
        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);

        $this->actingAs($driverA->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload([
                'driver_id' => $driverB->id,
            ]))
            ->assertCreated();

        $this->assertDatabaseMissing('taxi_driver_locations', ['driver_id' => $driverB->id]);
        $this->assertDatabaseHas('taxi_driver_locations', ['driver_id' => $driverA->id]);
    }

    public function test_latest_location_resolves_correctly(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload([
                'latitude' => 27.1000000,
                'captured_at' => now()->subMinutes(10)->toISOString(),
            ]))
            ->assertCreated();

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload(['latitude' => 27.2000000]))
            ->assertCreated();

        $this->assertSame(2, TaxiDriverLocation::where('driver_id', $driver->id)->count());

        $latest = app(TaxiDriverLocationService::class)->latestFor($driver->fresh());
        $this->assertSame('27.2000000', $latest->latitude);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.location.status', absolute: false))
            ->assertOk()
            ->assertJsonPath('freshness', 'live');
    }

    public function test_stale_and_recent_state_calculated(): void
    {
        Setting::setValue('taxi.tracking_stale_seconds', '120');

        $profile = VendorProfile::factory()->create();
        $freshDriver = $this->makeDriver($profile);
        $staleDriver = $this->makeDriver($profile);

        $this->actingAs($freshDriver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertCreated()
            ->assertJsonPath('freshness', 'live');

        $this->actingAs($staleDriver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload([
                'captured_at' => now()->subHour()->toISOString(),
            ]))
            ->assertCreated()
            ->assertJsonPath('freshness', 'stale');

        $this->assertSame('live', app(TaxiDriverLocationService::class)->freshness(
            TaxiDriverLocation::where('driver_id', $freshDriver->id)->first()
        ));
        $this->assertSame('offline', app(TaxiDriverLocationService::class)->freshness(null));
    }

    public function test_admin_tracking_page_renders(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile, ['reference' => 'TX-TRK-001']);
        app(TaxiBookingService::class)->assign($booking, $driver, $this->vehicleFor($profile));

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertCreated();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Taxi/Tracking/Index')
                ->has('board.data', 1)
                ->where('board.data.0.driver_id', $driver->id)
                ->where('board.data.0.freshness', 'live')
                ->where('board.data.0.trip.reference', 'TX-TRK-001'));
    }

    public function test_vendor_tracking_page_renders(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertCreated();

        $this->actingAs($vendor)
            ->get(route('vendor.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vendor/Taxi/Tracking/Index')
                ->has('board.data', 1)
                ->where('board.data.0.driver_id', $driver->id));
    }

    public function test_vendor_sees_only_own_drivers(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $driverA = $this->makeDriver($vendorA->vendorProfile);
        $driverB = $this->makeDriver($vendorB->vendorProfile);

        $this->actingAs($driverA->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertCreated();

        $this->actingAs($vendorB)
            ->get(route('vendor.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vendor/Taxi/Tracking/Index')
                ->has('board.data', 1)
                ->where('board.data.0.driver_id', $driverB->id)
                ->where('board.data.0.freshness', 'offline'));
    }

    public function test_cross_vendor_location_access_rejected(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $driverA = $this->makeDriver($vendorA->vendorProfile);

        $this->actingAs($driverA->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload([
                'latitude' => 12.3456789,
                'longitude' => 98.7654321,
            ]))
            ->assertCreated();

        $this->actingAs($vendorB)
            ->get(route('vendor.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertDontSee('12.3456789', false)
            ->assertDontSee('98.7654321', false);
    }

    public function test_driver_sees_own_tracking_status(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.location.status', absolute: false))
            ->assertOk()
            ->assertJsonPath('sharing', false)
            ->assertJsonPath('freshness', 'offline');

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertCreated();

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.location.status', absolute: false))
            ->assertOk()
            ->assertJsonPath('sharing', true)
            ->assertJsonPath('freshness', 'live')
            ->assertJsonStructure(['stale_seconds', 'update_interval_seconds', 'last_captured_at']);
    }

    public function test_customer_and_public_cannot_access_tracking(): void
    {
        $this->get(route('driver.taxi.location.status', absolute: false))
            ->assertRedirect();

        $this->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertUnauthorized();

        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get(route('admin.taxi.tracking.index', absolute: false))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('vendor.taxi.tracking.index', absolute: false))
            ->assertForbidden();
    }

    public function test_module_disabled_blocks_tracking(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertNotFound();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.tracking.index', absolute: false))
            ->assertNotFound();
    }

    public function test_no_audit_spam_per_ping(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $before = ActivityLog::count();

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload())
            ->assertCreated();

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), $this->pingPayload(['latitude' => 27.2000000]))
            ->assertCreated();

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_latest_query_avoids_full_history(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        foreach ([50, 40, 30, 20, 10] as $minutes) {
            TaxiDriverLocation::create([
                'driver_id' => $driver->id,
                'latitude' => 27.1000000,
                'longitude' => 78.0000000,
                'captured_at' => now()->subMinutes($minutes),
                'received_at' => now()->subMinutes($minutes),
            ]);
        }

        DB::enableQueryLog();
        $latest = app(TaxiDriverLocationService::class)->latestFor($driver->fresh());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(10, (int) $latest->captured_at->diffInMinutes(now()));
        $this->assertLessThanOrEqual(2, count($queries));
    }

    public function test_retention_purge_behavior(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);

        $old = TaxiDriverLocation::create([
            'driver_id' => $driver->id,
            'taxi_booking_id' => $booking->id,
            'latitude' => 27.1000000,
            'longitude' => 78.0000000,
            'captured_at' => now()->subDays(40),
            'received_at' => now()->subDays(40),
        ]);
        $recent = TaxiDriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => 27.2000000,
            'longitude' => 78.1000000,
            'captured_at' => now()->subHour(),
            'received_at' => now()->subHour(),
        ]);

        $purged = app(TaxiDriverLocationService::class)->purgeExpired();

        $this->assertSame(1, $purged);
        $this->assertDatabaseMissing('taxi_driver_locations', ['id' => $old->id]);
        $this->assertDatabaseHas('taxi_driver_locations', ['id' => $recent->id]);
        $this->assertDatabaseHas('taxi_bookings', ['id' => $booking->id]);
    }
}
