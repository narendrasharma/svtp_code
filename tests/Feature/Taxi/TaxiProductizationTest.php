<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverPayout;
use App\Models\TaxiReview;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\TaxiRouteService;
use App\Support\TaxiSettings;
use Database\Seeders\TaxiDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Taxi productization regression net (Phase 12A.13).
 *
 * Small, stable, cross-cutting guards only — feature behavior stays in
 * the per-phase suites. Each test is independent and sqlite-safe.
 */
class TaxiProductizationTest extends TestCase
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

    protected function bookingFor(VendorProfile $profile, array $overrides = []): TaxiBooking
    {
        return TaxiBooking::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'status' => TaxiBookingStatus::Confirmed->value,
            'pickup_at' => now()->addDay(),
            'passenger_count' => 2,
            'currency' => 'INR',
            'total_amount' => 2500,
        ], $overrides));
    }

    // ---- 1 module disabled blocks admin taxi routes --------------------

    public function test_module_disabled_blocks_admin_taxi_routes(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.taxi.bookings.index', absolute: false))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.taxi.dashboard', absolute: false))->assertNotFound();
    }

    // ---- 2 enabled admin routes resolve ---------------------------------

    public function test_module_enabled_admin_taxi_routes_resolve(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.taxi.bookings.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('admin.taxi.settings.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('admin.taxi.reviews.index', absolute: false))->assertOk();
    }

    // ---- 3 vendor routes resolve ------------------------------------------

    public function test_vendor_taxi_routes_resolve(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($vendor)->get(route('vendor.taxi.dashboard', absolute: false))->assertOk();
        $this->actingAs($vendor)->get(route('vendor.taxi.bookings.index', absolute: false))->assertOk();
        $this->actingAs($vendor)->get(route('vendor.taxi.reviews.index', absolute: false))->assertOk();
    }

    // ---- 4 driver routes resolve --------------------------------------------

    public function test_driver_taxi_routes_resolve(): void
    {
        $vendor = $this->makeVendor();
        $user = User::factory()->create(['role' => 'customer']);
        Driver::factory()->create([
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'user_id' => $user->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('driver.taxi.dashboard', absolute: false))->assertOk();
        $this->actingAs($user)->get(route('driver.taxi.reviews.index', absolute: false))->assertOk();
    }

    // ---- 5 customer ownership --------------------------------------------------

    public function test_customer_cannot_open_foreign_taxi_booking(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $intruder = User::factory()->create(['role' => 'customer']);
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('account.taxi.changes.show', $booking, absolute: false))->assertNotFound();
    }

    // ---- 6 vendor isolation -------------------------------------------------------

    public function test_vendor_cannot_open_foreign_taxi_booking(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)->get(route('vendor.taxi.bookings.show', $booking, absolute: false))->assertNotFound();
    }

    // ---- 7 tracking token read-only ------------------------------------------------------

    public function test_tracking_status_endpoint_rejects_writes(): void
    {
        $token = str_repeat('Ab3', 10);

        $this->post("/taxi/track/{$token}/status", ['overall_rating' => 5])->assertStatus(405);
        $this->post("/taxi/track/{$token}", ['status' => 'cancelled'])->assertStatus(405);
    }

    // ---- 8 no server map key leaked -----------------------------------------------------------------

    public function test_browser_map_config_exposes_no_server_secret(): void
    {
        Setting::setValue('taxi.maps.provider', 'google');
        Setting::setValue('taxi.maps.enabled', '1');
        Setting::setValue('taxi.maps.google.browser_key', 'PUBLIC-BROWSER-KEY');
        Setting::setValue('taxi.maps.google.server_key', 'SECRET-SERVER-KEY-12A13');

        $config = app(TaxiRouteService::class)->browserMapConfig();
        $encoded = json_encode($config);

        $this->assertStringNotContainsString('SECRET-SERVER-KEY-12A13', $encoded);
    }

    // ---- 9 no client-specific defaults --------------------------------------------------------------------

    public function test_settings_defaults_carry_no_client_data(): void
    {
        $encoded = json_encode(TaxiSettings::DEFAULTS);

        $this->assertStringNotContainsString('Mathura', $encoded);
        $this->assertStringNotContainsString('Vrindavan', $encoded);
        $this->assertStringNotContainsString('Shree', $encoded);
        $this->assertStringNotContainsString('₹', $encoded);
        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', (string) TaxiSettings::DEFAULTS['taxi.default_currency']);
    }

    // ---- 10 settings defaults available --------------------------------------------------------------------------

    public function test_all_taxi_settings_have_defaults(): void
    {
        foreach ([
            'taxi.booking_enabled', 'taxi.default_currency',
            'taxi.maps.provider', 'taxi.routing.enabled',
            'taxi.dispatch.smart_enabled', 'taxi.dispatch.auto_enabled',
            'taxi.dispatch.offer_enabled', 'taxi.dispatch.require_driver_acceptance',
            'taxi.customer_tracking.enabled',
            'taxi.driver_earnings.auto_payable_on_complete',
            'taxi.cancellation.enabled', 'taxi.refunds.enabled', 'taxi.reschedule.enabled',
            'taxi.reviews.enabled', 'taxi.reviews.require_moderation',
            'taxi.reviews.review_window_days', 'taxi.reviews.allow_text_review',
            'taxi.reviews.allow_vendor_reply', 'taxi.reviews.low_rating_threshold',
        ] as $key) {
            $this->assertNotNull(TaxiSettings::get($key), "Missing default for {$key}.");
        }
    }

    // ---- 11 scheduler command registered + module-aware -------------------------------------------------------------------

    public function test_expire_offers_command_is_registered_and_module_aware(): void
    {
        $this->assertArrayHasKey('taxi:expire-dispatch-offers', Artisan::all());

        Setting::setValue('modules.taxi.enabled', '0');
        $this->artisan('taxi:expire-dispatch-offers')->assertExitCode(0);

        Setting::setValue('modules.taxi.enabled', '1');
        $this->artisan('taxi:expire-dispatch-offers')->assertExitCode(0);
    }

    // ---- 12 demo seeder idempotent ----------------------------------------------------------------------------

    public function test_demo_seeder_runs_idempotently(): void
    {
        $this->seed(TaxiDemoSeeder::class);
        $this->assertSame(1, VendorProfile::where('slug', 'demo-cabs')->count());
        $this->assertSame(3, TaxiBooking::where('reference', 'like', 'TX-DEMO-%')->count());

        $this->seed(TaxiDemoSeeder::class);
        $this->assertSame(1, VendorProfile::where('slug', 'demo-cabs')->count());
        $this->assertSame(3, TaxiBooking::where('reference', 'like', 'TX-DEMO-%')->count());
        $this->assertSame(1, TaxiReview::whereHas('booking', fn ($q) => $q->where('reference', 'TX-DEMO-DONE'))->count());
    }

    // ---- 13 financial pages authorized --------------------------------------------------------------------------

    public function test_finance_pages_require_proper_identity(): void
    {
        $this->get(route('admin.taxi.earnings.index', absolute: false))->assertRedirect();

        $vendor = $this->makeVendor();
        $this->actingAs($vendor)->get(route('admin.taxi.payouts.index', absolute: false))->assertForbidden();
    }

    // ---- 14 review pages authorized --------------------------------------------------------------------------------

    public function test_review_pages_require_proper_identity(): void
    {
        $this->get(route('account.taxi.reviews.index', absolute: false))->assertRedirect();

        $vendor = $this->makeVendor();
        $this->actingAs($vendor)->get(route('admin.taxi.reviews.index', absolute: false))->assertForbidden();
    }

    // ---- 15 foreign finance blocked ------------------------------------------------------------------------------------

    public function test_vendor_cannot_open_foreign_payout(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $driver = Driver::factory()->create(['vendor_profile_id' => $vendorA->vendorProfile->id]);
        $payout = TaxiDriverPayout::create([
            'payout_number' => 'TP-PROD-0001',
            'driver_id' => $driver->id,
            'vendor_profile_id' => $vendorA->vendorProfile->id,
            'currency' => 'INR',
            'amount' => '100.00',
            'status' => 'draft',
        ]);

        $this->actingAs($vendorB)->get(route('vendor.taxi.payouts.show', $payout, absolute: false))->assertNotFound();
    }
}
