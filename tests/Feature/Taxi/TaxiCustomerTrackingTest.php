<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingNote;
use App\Models\TaxiDriverLocation;
use App\Models\TaxiTrackingToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use App\Services\TaxiTrackingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 12A.9 customer live tracking via revocable tokens (no login).
 *
 * Run ONE method at a time inside the OS memory cage — never the whole
 * file (see Phase 12A.3 OOM history). Routing provider calls are faked.
 */
class TaxiCustomerTrackingTest extends TestCase
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
            'first_name' => 'Ramesh',
            'last_name' => 'Kumar',
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ])->fresh();
    }

    protected function makeVehicle(VendorProfile $profile): Vehicle
    {
        return Vehicle::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => 'available',
            'is_active' => true,
            'passenger_capacity' => 4,
        ]);
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

    protected function enableTracking(array $overrides = []): void
    {
        foreach (array_merge(['taxi.customer_tracking.enabled' => '1'], $overrides) as $key => $value) {
            Setting::setValue($key, $value);
        }
    }

    protected function generate(TaxiBooking $booking): array
    {
        return app(TaxiTrackingTokenService::class)->generate($booking);
    }

    protected function ping(Driver $driver, float $lat, float $lng, ?string $capturedAt = null): void
    {
        TaxiDriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'captured_at' => $capturedAt ?? now()->toDateTimeString(),
            'received_at' => now()->toDateTimeString(),
        ]);
    }

    protected function assignTrip(TaxiBooking $booking, Driver $driver, Vehicle $vehicle): void
    {
        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle);
    }

    protected function moveTo(TaxiBooking $booking, string $status): void
    {
        app(TaxiBookingService::class)->changeStatus($booking->fresh(), TaxiBookingStatus::from($status));
    }

    public function test_tracking_disabled_blocks_public_page(): void
    {
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = app(TaxiTrackingTokenService::class)->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])->assertNotFound();
    }

    public function test_valid_token_opens_correct_booking(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile, ['reference' => 'TX-TRK-CUST-001']);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Taxi/Track/Show')
                ->where('reference', 'TX-TRK-CUST-001')
                ->has('tracking'));
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->enableTracking();

        $this->get('/taxi/track/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/taxi/track/short')->assertNotFound();
    }

    public function test_revoked_token_blocked(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])->assertOk();

        app(TaxiTrackingTokenService::class)->revoke($generated['record']->fresh());

        $this->get('/taxi/track/'.$generated['token'])->assertNotFound();
    }

    public function test_expired_token_blocked(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);
        $generated['record']->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get('/taxi/track/'.$generated['token'])->assertNotFound();
    }

    public function test_token_not_guessable_or_sequential(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $first = $this->generate($booking);
        $second = $this->generate($booking);

        $this->assertNotSame($first['token'], $second['token']);
        $this->assertSame(48, strlen($first['token']));
        $this->assertStringNotContainsString((string) $booking->id, $first['token']);
    }

    public function test_raw_token_not_stored(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);

        $this->assertDatabaseHas('taxi_tracking_tokens', [
            'taxi_booking_id' => $booking->id,
            'token_hash' => hash('sha256', $generated['token']),
        ]);
        $this->assertDatabaseMissing('taxi_tracking_tokens', ['token_hash' => $generated['token']]);
    }

    public function test_admin_can_generate_token(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $response = $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.tracking.store', $booking, absolute: false));

        $response->assertRedirect();
        $response->assertSessionHas('tracking_url');
        $this->assertSame(1, TaxiTrackingToken::where('taxi_booking_id', $booking->id)->active()->count());
    }

    public function test_vendor_can_generate_for_own_booking(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $response = $this->actingAs($vendor)
            ->post(route('vendor.taxi.bookings.tracking.store', $booking, absolute: false));

        $response->assertRedirect();
        $response->assertSessionHas('tracking_url');
        $this->assertSame(1, TaxiTrackingToken::where('taxi_booking_id', $booking->id)->active()->count());
    }

    public function test_vendor_cannot_generate_for_foreign_booking(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)
            ->post(route('vendor.taxi.bookings.tracking.store', $booking, absolute: false))
            ->assertNotFound();

        $this->assertDatabaseCount('taxi_tracking_tokens', 0);
    }

    public function test_regenerate_invalidates_old_token(): void
    {
        $this->enableTracking();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $first = $this->generate($booking);
        $second = $this->generate($booking);

        $this->get('/taxi/track/'.$first['token'])->assertNotFound();
        $this->get('/taxi/track/'.$second['token'])->assertOk();
    }

    public function test_customer_payload_excludes_internal_fields(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertDontSee('"total_amount"', false)
            ->assertDontSee('pricing_snapshot', false)
            ->assertDontSee('vendor_profile_id', false)
            ->assertDontSee('taxi_booking_id', false);
    }

    public function test_customer_cannot_see_internal_notes(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        TaxiBookingNote::create([
            'taxi_booking_id' => $booking->id,
            'body' => 'Internal secret dispatcher note',
        ]);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertDontSee('Internal secret dispatcher note', false);
    }

    public function test_customer_cannot_see_pricing_rules(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $booking->forceFill([
            'pricing_snapshot' => ['version' => 1, 'marker' => 'SNAPSHOT-MARKER-XYZ'],
        ])->save();
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertDontSee('SNAPSHOT-MARKER-XYZ', false);
    }

    public function test_driver_location_hidden_before_allowed_status(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonPath('tracking.state', 'hidden')
            ->assertJsonPath('tracking.location', null)
            ->assertJsonPath('driver.display_name', 'Ramesh K.');
    }

    public function test_driver_location_visible_during_active_status(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonPath('tracking.state', 'live')
            ->assertJsonPath('tracking.location.latitude', '27.1760000');
    }

    public function test_reassignment_switches_visible_driver(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);
        $driverB->update(['first_name' => 'Suresh', 'last_name' => 'Patel']);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driverA, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->ping($driverA, 27.1000000, 78.0000000);

        app(TaxiBookingService::class)->assign($booking->fresh(), $driverB, $this->makeVehicle($profile));
        $this->ping($driverB, 27.1800000, 78.0500000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonPath('driver.display_name', 'Suresh P.')
            ->assertJsonPath('tracking.location.latitude', '27.1800000');
    }

    public function test_old_driver_location_hidden_after_reassignment(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driverA, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);

        app(TaxiBookingService::class)->assign($booking->fresh(), $driverB, $this->makeVehicle($profile));
        $this->ping($driverA, 11.1111111, 22.2222222);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonMissing(['latitude' => '11.1111111']);
    }

    public function test_completed_trip_stops_live_location(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->moveTo($booking, TaxiBookingStatus::Arrived->value);
        $this->moveTo($booking, TaxiBookingStatus::PassengerOnBoard->value);
        $this->moveTo($booking, TaxiBookingStatus::Completed->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonPath('status', TaxiBookingStatus::Completed->value)
            ->assertJsonPath('tracking.state', 'ended')
            ->assertJsonPath('tracking.location', null);
    }

    public function test_cancelled_trip_stops_live_location(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::Cancelled->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonPath('tracking.state', 'ended')
            ->assertJsonPath('tracking.location', null);
    }

    public function test_provider_none_page_works(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Taxi/Track/Show')
                ->where('tracking.state', 'live'))
            ->assertDontSee('Map provider failed', false);
    }

    public function test_routing_failure_page_works(): void
    {
        $this->enableTracking([
            'taxi.maps.provider' => 'google',
            'taxi.maps.enabled' => '1',
            'taxi.routing.enabled' => '1',
            'taxi.maps.google.server_key' => 'test-server-key',
            'taxi.maps.google.browser_key' => 'test-browser-key',
        ]);
        Http::fake(['maps.googleapis.com/*' => Http::response(null, 500)]);

        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonPath('tracking.state', 'live')
            ->assertJsonPath('route.available', false)
            ->assertJsonPath('route.reason', 'Route unavailable.');
    }

    public function test_stale_location_rendered_safely(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->ping($driver, 27.1760000, 78.0430000, now()->subHour()->toDateTimeString());
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertJsonPath('tracking.state', 'stale')
            ->assertJsonPath('tracking.location.latitude', '27.1760000');
    }

    public function test_refresh_endpoint_returns_minimal_payload(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);

        $response = $this->get('/taxi/track/'.$generated['token'].'/status')->assertOk();

        $this->assertSame(
            ['reference', 'status', 'status_label', 'trip_type', 'pickup_at', 'pickup_address', 'drop_address', 'driver', 'tracking', 'route', 'map', 'refresh_seconds'],
            array_keys($response->json())
        );
        $response->assertJsonMissingPath('notes');
    }

    public function test_refresh_endpoint_rate_limited(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);
        $url = '/taxi/track/'.$generated['token'].'/status';

        for ($i = 0; $i < 30; $i++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertStatus(429);
    }

    public function test_token_user_cannot_access_admin_resources(): void
    {
        $this->get('/admin/taxi/bookings')->assertRedirect();
        $this->get('/vendor/taxi/bookings')->assertRedirect();
    }

    public function test_cross_booking_token_tampering_blocked(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $bookingA = $this->bookingFor($profile, ['reference' => 'TX-TRK-A-001']);
        $bookingB = $this->bookingFor($profile, ['reference' => 'TX-TRK-B-002']);
        $generated = $this->generate($bookingA);

        $tampered = substr($generated['token'], 0, 47).($generated['token'][47] === 'a' ? 'b' : 'a');

        $this->get('/taxi/track/'.$tampered)->assertNotFound();
        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertDontSee('TX-TRK-B-002', false);
    }

    public function test_module_disabled_returns_safe_failure(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = app(TaxiTrackingTokenService::class)->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])->assertNotFound();
        $this->get('/taxi/track/'.$generated['token'].'/status')->assertNotFound();
    }

    public function test_tracking_generation_audited(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.tracking.store', $booking, absolute: false))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'taxi_tracking.generated',
            'module' => 'taxi',
        ]);
    }

    public function test_polling_is_not_audit_spammed(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);

        $before = ActivityLog::count();

        $this->get('/taxi/track/'.$generated['token'].'/status')->assertOk();
        $this->get('/taxi/track/'.$generated['token'].'/status')->assertOk();
        $this->get('/taxi/track/'.$generated['token'].'/status')->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_privacy_headers_present(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_server_secret_never_in_page_props(): void
    {
        $this->enableTracking([
            'taxi.maps.provider' => 'google',
            'taxi.maps.enabled' => '1',
            'taxi.routing.enabled' => '1',
            'taxi.maps.google.server_key' => 'test-server-key',
            'taxi.maps.google.browser_key' => 'test-browser-key',
        ]);
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertDontSee('test-server-key', false);

        $this->get('/taxi/track/'.$generated['token'].'/status')
            ->assertOk()
            ->assertDontSee('test-server-key', false);
    }

    public function test_tracking_link_does_not_mutate_pricing_snapshot(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $booking = $this->bookingFor($profile);
        $booking->forceFill([
            'total_amount' => '65.00',
            'pricing_snapshot' => ['version' => 1, 'grand_total' => '65.00'],
        ])->save();
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])->assertOk();
        $this->get('/taxi/track/'.$generated['token'].'/status')->assertOk();

        $this->assertSame('65.00', $booking->fresh()->total_amount);
        $this->assertSame(['version' => 1, 'grand_total' => '65.00'], $booking->fresh()->pricing_snapshot);
    }

    public function test_completed_summary_without_coordinates(): void
    {
        $this->enableTracking();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile, ['reference' => 'TX-TRK-DONE-001']);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->moveTo($booking, TaxiBookingStatus::Arrived->value);
        $this->moveTo($booking, TaxiBookingStatus::PassengerOnBoard->value);
        $this->moveTo($booking, TaxiBookingStatus::Completed->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Taxi/Track/Show')
                ->where('reference', 'TX-TRK-DONE-001')
                ->where('status', TaxiBookingStatus::Completed->value)
                ->where('tracking.state', 'ended'));
    }

    public function test_public_refresh_makes_no_routing_network_call(): void
    {
        $this->enableTracking();
        Http::fake();
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $this->makeVehicle($profile));
        $this->moveTo($booking, TaxiBookingStatus::EnRoute->value);
        $this->ping($driver, 27.1760000, 78.0430000);
        $generated = $this->generate($booking);

        $this->get('/taxi/track/'.$generated['token'].'/status')->assertOk();

        Http::assertNothingSent();
    }
}
