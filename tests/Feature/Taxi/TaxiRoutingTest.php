<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use App\Services\TaxiRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 12A.6 maps + ETA provider integration (no real network calls —
 * the Google Directions endpoint is always faked).
 *
 * Run ONE method at a time inside the OS memory cage — never the whole
 * file (see Phase 12A.3 OOM history).
 */
class TaxiRoutingTest extends TestCase
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

    protected function makeDriver(VendorProfile $profile): Driver
    {
        $user = User::factory()->create(['role' => 'customer']);

        return Driver::factory()->create([
            'user_id' => $user->id,
            'vendor_profile_id' => $profile->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ])->fresh();
    }

    protected function bookingFor(VendorProfile $profile, array $overrides = []): TaxiBooking
    {
        return TaxiBooking::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'status' => TaxiBookingStatus::Confirmed->value,
            'pickup_at' => now()->addHours(5),
            'passenger_count' => 2,
            'pickup_lat' => 27.1751000,
            'pickup_lng' => 78.0421000,
            'drop_lat' => 27.2000000,
            'drop_lng' => 78.1000000,
        ], $overrides));
    }

    protected function enableRouting(string $provider = 'google'): void
    {
        Setting::setValue('taxi.maps.provider', $provider);
        Setting::setValue('taxi.maps.enabled', '1');
        Setting::setValue('taxi.routing.enabled', '1');
        Setting::setValue('taxi.maps.google.server_key', 'test-server-key');
        Setting::setValue('taxi.maps.google.browser_key', 'test-browser-key');
    }

    protected function fakeDirections(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [[
                    'overview_polyline' => ['points' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@'],
                    'legs' => [[
                        'distance' => ['value' => 12500],
                        'duration' => ['value' => 1500],
                    ]],
                ]],
            ], 200),
        ]);
    }

    public function test_provider_none_returns_graceful_unavailable(): void
    {
        $result = app(TaxiRouteService::class)->route(27.1751, 78.0421, 27.20, 78.10);

        $this->assertFalse($result->available);
        $this->assertSame('none', $result->provider);
        $this->assertNotEmpty($result->reason);
        $this->assertNull($result->distanceMeters);
        $this->assertNull($result->etaLabel());
    }

    public function test_routing_service_returns_normalized_shape(): void
    {
        $this->enableRouting();
        $this->fakeDirections();

        $shape = app(TaxiRouteService::class)->route(27.1751, 78.0421, 27.20, 78.10)->toArray();

        $this->assertSame(['provider', 'available', 'reason', 'distance_meters', 'duration_seconds', 'eta', 'polyline', 'path', 'bounds', 'calculated_at', 'cached'], array_keys($shape));
        $this->assertTrue($shape['available']);
        $this->assertSame(12500, $shape['distance_meters']);
        $this->assertSame(1500, $shape['duration_seconds']);
        $this->assertSame('≈25 min', $shape['eta']);
        $this->assertNotEmpty($shape['path']);
        $this->assertNotNull($shape['bounds']);
    }

    public function test_disabled_routing_does_not_call_provider(): void
    {
        Setting::setValue('taxi.maps.provider', 'google');
        Setting::setValue('taxi.routing.enabled', '0');
        Setting::setValue('taxi.maps.google.server_key', 'test-server-key');
        $this->fakeDirections();

        $result = app(TaxiRouteService::class)->route(27.1751, 78.0421, 27.20, 78.10);

        $this->assertFalse($result->available);
        Http::assertNothingSent();
    }

    public function test_invalid_provider_credential_handled_safely(): void
    {
        Setting::setValue('taxi.maps.provider', 'google');
        Setting::setValue('taxi.routing.enabled', '1');
        Setting::setValue('taxi.maps.google.server_key', '');
        $this->fakeDirections();

        $result = app(TaxiRouteService::class)->route(27.1751, 78.0421, 27.20, 78.10);

        $this->assertFalse($result->available);
        $this->assertNotEmpty($result->reason);
        Http::assertNothingSent();
    }

    public function test_provider_exception_degrades_gracefully(): void
    {
        $this->enableRouting();
        Http::fake(['maps.googleapis.com/*' => Http::response(null, 500)]);

        $result = app(TaxiRouteService::class)->route(27.1751, 78.0421, 27.20, 78.10);

        $this->assertFalse($result->available);
        $this->assertSame('google', $result->provider);
    }

    public function test_route_caching_works(): void
    {
        $this->enableRouting();
        $this->fakeDirections();

        $service = app(TaxiRouteService::class);
        $first = $service->route(27.1751, 78.0421, 27.20, 78.10);
        $second = $service->route(27.1751, 78.0421, 27.20, 78.10);

        $this->assertTrue($first->available);
        $this->assertTrue($second->cached);
        $this->assertSame($first->distanceMeters, $second->distanceMeters);
        Http::assertSentCount(1);
    }

    public function test_admin_tracking_map_page_renders(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Taxi/Tracking/Index')
                ->has('board')
                ->has('map'));
    }

    public function test_vendor_tracking_map_page_renders(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($vendor)
            ->get(route('vendor.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vendor/Taxi/Tracking/Index')
                ->has('board')
                ->has('map'));
    }

    public function test_vendor_receives_no_foreign_driver_coords(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $driverA = $this->makeDriver($vendorA->vendorProfile);

        $this->actingAs($driverA->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), [
                'latitude' => 12.3456789,
                'longitude' => 98.7654321,
            ])
            ->assertCreated();

        $this->actingAs($vendorB)
            ->get(route('vendor.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertDontSee('12.3456789', false)
            ->assertDontSee('98.7654321', false);
    }

    public function test_driver_active_trip_map_only_resolves_own_trip(): void
    {
        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);
        $bookingA = $this->bookingFor($profile);
        $service = app(TaxiBookingService::class);
        $service->assign($bookingA, $driverA, Vehicle::factory()->create([
            'vendor_profile_id' => $profile->id, 'status' => 'available', 'is_active' => true, 'passenger_capacity' => 4,
        ]));

        $this->actingAs($driverB->user)
            ->get(route('driver.taxi.trips.route', $bookingA, absolute: false))
            ->assertNotFound();

        $this->actingAs($driverA->user)
            ->get(route('driver.taxi.trips.route', $bookingA, absolute: false))
            ->assertOk()
            ->assertJsonPath('markers.0.kind', 'pickup');
    }

    public function test_unauthorized_user_blocked(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);

        $this->actingAs($customer)
            ->get(route('admin.taxi.tracking.route', ['taxi_booking' => $booking->id], absolute: false))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('driver.taxi.trips.route', $booking, absolute: false))
            ->assertForbidden();
    }

    public function test_server_secret_never_in_inertia_props(): void
    {
        $this->enableRouting();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertDontSee('test-server-key', false);

        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        app(TaxiBookingService::class)->assign($booking, $driver, Vehicle::factory()->create([
            'vendor_profile_id' => $profile->id, 'status' => 'available', 'is_active' => true, 'passenger_capacity' => 4,
        ]));

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.show', $booking, absolute: false))
            ->assertOk()
            ->assertDontSee('test-server-key', false);
    }

    public function test_stale_driver_renders_with_stale_state(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), [
                'latitude' => 27.1751000,
                'longitude' => 78.0421000,
                'captured_at' => now()->subHour()->toISOString(),
            ])
            ->assertCreated();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.tracking.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.data.0.freshness', 'stale'));
    }

    public function test_active_trip_route_uses_current_driver_location(): void
    {
        $this->enableRouting();
        $this->fakeDirections();

        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        app(TaxiBookingService::class)->assign($booking, $driver, Vehicle::factory()->create([
            'vendor_profile_id' => $profile->id, 'status' => 'available', 'is_active' => true, 'passenger_capacity' => 4,
        ]));

        $this->actingAs($driver->user)
            ->postJson(route('driver.taxi.location.store', absolute: false), [
                'latitude' => 27.5000000,
                'longitude' => 78.5000000,
            ])
            ->assertCreated();

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.route', $booking, absolute: false))
            ->assertOk()
            ->assertJsonPath('markers.0.kind', 'driver')
            ->assertJsonPath('markers.0.lat', 27.5)
            ->assertJsonPath('route.available', true)
            ->assertJsonPath('route.distance_meters', 12500);
    }

    public function test_completed_trip_does_not_refresh_routing(): void
    {
        $this->enableRouting();
        $this->fakeDirections();

        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $service = app(TaxiBookingService::class);
        $service->assign($booking, $driver, Vehicle::factory()->create([
            'vendor_profile_id' => $profile->id, 'status' => 'available', 'is_active' => true, 'passenger_capacity' => 4,
        ]));
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::EnRoute);
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::Arrived);
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::PassengerOnBoard);
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::Completed);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.route', $booking, absolute: false))
            ->assertOk()
            ->assertJsonPath('route', null);

        Http::assertNothingSent();
    }

    public function test_module_disabled_behavior(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.tracking.route', ['taxi_booking' => $booking->id], absolute: false))
            ->assertNotFound();

        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.route', $booking, absolute: false))
            ->assertNotFound();
    }

    public function test_routing_does_not_mutate_pricing_snapshot(): void
    {
        $this->enableRouting();
        $this->fakeDirections();

        $booking = TaxiBooking::factory()->create([
            'status' => TaxiBookingStatus::DriverAssigned->value,
            'pickup_lat' => 27.1751000,
            'pickup_lng' => 78.0421000,
            'drop_lat' => 27.2000000,
            'drop_lng' => 78.1000000,
        ]);
        $booking->forceFill([
            'total_amount' => '65.00',
            'pricing_snapshot' => ['version' => 1, 'grand_total' => '65.00'],
        ])->save();

        $result = app(TaxiRouteService::class)->routeForBooking($booking->fresh());

        $this->assertTrue($result->available);
        $this->assertSame('65.00', $booking->fresh()->total_amount);
        $this->assertSame(['version' => 1, 'grand_total' => '65.00'], $booking->fresh()->pricing_snapshot);
    }

    public function test_public_customer_cannot_access_tracking_map(): void
    {
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);

        $this->get(route('admin.taxi.tracking.index', absolute: false))
            ->assertRedirect();

        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get(route('vendor.taxi.tracking.route', ['taxi_booking' => $booking->id], absolute: false))
            ->assertForbidden();
    }
}
