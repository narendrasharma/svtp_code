<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 12A.7 smart dispatch recommendations (decision support only).
 *
 * Run ONE method at a time inside the OS memory cage — never the whole
 * file (see Phase 12A.3 OOM history). Routing provider calls are faked;
 * no real Google network calls happen here.
 */
class TaxiSmartDispatchTest extends TestCase
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

    protected function makeDriver(VendorProfile $profile, array $overrides = []): Driver
    {
        $user = User::factory()->create(['role' => 'customer']);

        return Driver::factory()->create(array_merge([
            'user_id' => $user->id,
            'vendor_profile_id' => $profile->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ], $overrides))->fresh();
    }

    protected function makeVehicle(VendorProfile $profile, array $overrides = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'status' => 'available',
            'is_active' => true,
            'passenger_capacity' => 4,
        ], $overrides));
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
        ], $overrides));
    }

    protected function ping(Driver $driver, float $lat, float $lng, ?string $capturedAt = null): TaxiDriverLocation
    {
        return TaxiDriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'captured_at' => $capturedAt ?? now()->toDateTimeString(),
            'received_at' => now()->toDateTimeString(),
        ]);
    }

    protected function enableRouting(): void
    {
        Setting::setValue('taxi.maps.provider', 'google');
        Setting::setValue('taxi.routing.enabled', '1');
        Setting::setValue('taxi.maps.google.server_key', 'test-server-key');
        Setting::setValue('taxi.dispatch.use_routing_eta', '1');
    }

    protected function fakeDirections(int $meters = 8000, int $seconds = 900): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [[
                    'overview_polyline' => ['points' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@'],
                    'legs' => [[
                        'distance' => ['value' => $meters],
                        'duration' => ['value' => $seconds],
                    ]],
                ]],
            ], 200),
        ]);
    }

    public function test_eligible_driver_appears(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $response = $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false));

        $response->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.driver_id', $driver->id)
            ->assertJsonPath('recommendations.0.rank', 1);
    }

    public function test_inactive_driver_excluded(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile, ['is_active' => false]);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(0, 'recommendations');
    }

    public function test_on_leave_driver_excluded(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $driver->availabilities()->create([
            'from_at' => now()->subDay(),
            'to_at' => now()->addDays(2),
            'status' => 'on_leave',
        ]);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(0, 'recommendations');
    }

    public function test_conflicting_trip_driver_excluded(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $busy = $this->bookingFor($vendor->vendorProfile);
        app(TaxiBookingService::class)->assign($busy, $driver, $vehicle);

        $second = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $second, absolute: false))
            ->assertOk()
            ->assertJsonCount(0, 'recommendations');
    }

    public function test_maintenance_vehicle_excluded(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile, ['status' => 'maintenance']);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(0, 'recommendations');
    }

    public function test_insufficient_capacity_vehicle_excluded(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile, ['passenger_capacity' => 2]);
        $booking = $this->bookingFor($vendor->vendorProfile, ['passenger_count' => 5]);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(0, 'recommendations');
    }

    public function test_cross_vendor_driver_excluded(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $driverB = $this->makeDriver($vendorB->vendorProfile);
        $this->makeVehicle($vendorB->vendorProfile);
        $booking = $this->bookingFor($vendorA->vendorProfile);
        $this->ping($driverB, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(0, 'recommendations');
    }

    public function test_fresh_location_driver_ranked(): void
    {
        $vendor = $this->makeVendor();
        $fresh = $this->makeDriver($vendor->vendorProfile);
        $stale = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($fresh, 27.1760000, 78.0430000);
        $this->ping($stale, 27.1760000, 78.0430000, now()->subHour()->toDateTimeString());

        $response = $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false));

        $response->assertOk()
            ->assertJsonCount(2, 'recommendations')
            ->assertJsonPath('recommendations.0.driver_id', $fresh->id)
            ->assertJsonPath('recommendations.0.location_state', 'live')
            ->assertJsonPath('recommendations.1.driver_id', $stale->id)
            ->assertJsonPath('recommendations.1.location_state', 'stale');
    }

    public function test_nearer_driver_ranks_before_farther(): void
    {
        $vendor = $this->makeVendor();
        $near = $this->makeDriver($vendor->vendorProfile);
        $far = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($near, 27.1760000, 78.0430000);
        $this->ping($far, 27.5000000, 78.5000000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonPath('recommendations.0.driver_id', $near->id)
            ->assertJsonPath('recommendations.1.driver_id', $far->id);
    }

    public function test_stale_location_handled_safely(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000, now()->subHour()->toDateTimeString());

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.location_state', 'stale');
    }

    public function test_missing_location_fallback(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.driver_id', $driver->id)
            ->assertJsonPath('recommendations.0.location_state', 'offline')
            ->assertJsonPath('recommendations.0.pickup_eta_minutes', null);
    }

    public function test_routing_disabled_fallback_works(): void
    {
        Setting::setValue('taxi.maps.provider', 'google');
        Setting::setValue('taxi.routing.enabled', '1');
        Setting::setValue('taxi.maps.google.server_key', 'test-server-key');
        Setting::setValue('taxi.dispatch.use_routing_eta', '0');
        $this->fakeDirections();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonPath('recommendations.0.pickup_eta_minutes', null)
            ->assertJsonPath('recommendations.0.pickup_distance_km', 0.1);

        Http::assertNothingSent();
    }

    public function test_routing_provider_failure_fallback(): void
    {
        $this->enableRouting();
        Http::fake(['maps.googleapis.com/*' => Http::response(null, 500)]);

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.pickup_eta_minutes', null);
    }

    public function test_routing_api_only_called_for_shortlist(): void
    {
        $this->enableRouting();
        Setting::setValue('taxi.dispatch.routing_candidate_limit', '2');
        $this->fakeDirections();

        $vendor = $this->makeVendor();
        $profile = $vendor->vendorProfile;

        foreach ([27.1760000, 27.1800000, 27.3000000, 27.5000000] as $index => $lat) {
            $driver = $this->makeDriver($profile);
            $this->makeVehicle($profile);
            $this->ping($driver, $lat, 78.0430000);
        }

        $booking = $this->bookingFor($profile);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(4, 'recommendations');

        Http::assertSentCount(2);
    }

    public function test_max_pickup_radius_respected(): void
    {
        Setting::setValue('taxi.dispatch.max_pickup_radius_km', '5');

        $vendor = $this->makeVendor();
        $near = $this->makeDriver($vendor->vendorProfile);
        $far = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($near, 27.1760000, 78.0430000);
        $this->ping($far, 27.5000000, 78.5000000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.driver_id', $near->id);
    }

    public function test_admin_recommendations_render(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonStructure(['recommendations', 'excluded_counts'])
            ->assertJsonStructure(['recommendations' => [['rank', 'driver_id', 'driver_name', 'vehicle_id', 'vehicle_name', 'pickup_distance_km', 'pickup_eta_minutes', 'location_state', 'recommendation_reason']]]);
    }

    public function test_vendor_recommendations_render(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($vendor)
            ->getJson(route('vendor.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.driver_id', $driver->id);
    }

    public function test_vendor_cannot_receive_foreign_recommendations(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)
            ->getJson(route('vendor.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertNotFound();
    }

    public function test_one_click_assignment_succeeds(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $reco = $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->json('recommendations.0');

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $reco['driver_id'],
                'vehicle_id' => $reco['vehicle_id'],
            ])
            ->assertRedirect();

        $this->assertSame($driver->id, $booking->fresh()->assigned_driver_id);
        $this->assertSame($vehicle->id, $booking->fresh()->assigned_vehicle_id);
        $this->assertSame(TaxiBookingStatus::DriverAssigned->value, $booking->fresh()->status);
    }

    public function test_recommendation_revalidated_before_assignment(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $reco = $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->json('recommendations.0');

        $other = $this->bookingFor($vendor->vendorProfile);
        app(TaxiBookingService::class)->assign($other, $driver, $vehicle);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $reco['driver_id'],
                'vehicle_id' => $reco['vehicle_id'],
            ])
            ->assertSessionHasErrors('assignment');
    }

    public function test_newly_conflicting_driver_rejected_at_assignment(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $first = $this->bookingFor($vendor->vendorProfile);
        app(TaxiBookingService::class)->assign($first, $driver, $vehicle);

        $second = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $second, absolute: false), [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertSessionHasErrors('assignment');
    }

    public function test_provider_none_still_returns_useful_recommendations(): void
    {
        $vendor = $this->makeVendor();
        $near = $this->makeDriver($vendor->vendorProfile);
        $far = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($near, 27.1760000, 78.0430000);
        $this->ping($far, 27.5000000, 78.5000000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(2, 'recommendations')
            ->assertJsonPath('recommendations.0.driver_id', $near->id)
            ->assertJsonPath('recommendations.0.pickup_eta_minutes', null)
            ->assertJsonPath('recommendations.0.pickup_distance_km', 0.1);
    }

    public function test_no_eligible_drivers_returns_graceful_result(): void
    {
        $vendor = $this->makeVendor();
        $this->makeDriver($vendor->vendorProfile, ['is_active' => false]);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(0, 'recommendations')
            ->assertJsonStructure(['excluded_counts']);
    }

    public function test_module_disabled_blocks_smart_dispatch(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertNotFound();

        $this->actingAs($vendor)
            ->getJson(route('vendor.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertNotFound();
    }

    public function test_recommendation_does_not_modify_pricing_snapshot(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $booking->forceFill([
            'total_amount' => '65.00',
            'pricing_snapshot' => ['version' => 1, 'grand_total' => '65.00'],
        ])->save();
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.taxi.dispatch.recommendations', $booking, absolute: false))
            ->assertOk()
            ->assertJsonCount(1, 'recommendations');

        $this->assertSame('65.00', $booking->fresh()->total_amount);
        $this->assertSame(['version' => 1, 'grand_total' => '65.00'], $booking->fresh()->pricing_snapshot);
    }
}
