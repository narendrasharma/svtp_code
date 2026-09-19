<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Enums\TaxiDispatchOfferStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\TaxiDispatchOffer;
use App\Models\TaxiDriverLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use App\Services\TaxiAutoDispatchService;
use App\Services\TaxiBookingService;
use App\Services\TaxiDispatchRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 12A.8 controlled auto-dispatch + driver offer/accept.
 *
 * Run ONE method at a time inside the OS memory cage — never the whole
 * file (see Phase 12A.3 OOM history). No real network calls happen here.
 */
class TaxiAutoDispatchTest extends TestCase
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

    protected function ping(Driver $driver, float $lat, float $lng): void
    {
        TaxiDriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'captured_at' => now()->toDateTimeString(),
            'received_at' => now()->toDateTimeString(),
        ]);
    }

    protected function enableAuto(array $overrides = []): void
    {
        foreach (array_merge([
            'taxi.dispatch.auto_enabled' => '1',
            'taxi.dispatch.offer_enabled' => '1',
            'taxi.dispatch.offer_timeout_seconds' => '120',
            'taxi.dispatch.max_offer_attempts' => '3',
            'taxi.dispatch.require_driver_acceptance' => '1',
            'taxi.dispatch.auto_fallback_manual' => '1',
            'taxi.dispatch.smart_enabled' => '1',
        ], $overrides) as $key => $value) {
            Setting::setValue($key, $value);
        }
    }

    protected function startOffer(TaxiBooking $booking, ?User $actor = null): TaxiDispatchOffer
    {
        return app(TaxiAutoDispatchService::class)->startForBooking($booking, $actor, 'manual');
    }

    public function test_auto_dispatch_disabled_by_default(): void
    {
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertSessionHasErrors('auto_dispatch');

        $this->assertDatabaseCount('taxi_dispatch_offers', 0);
    }

    public function test_admin_can_start_auto_dispatch(): void
    {
        $this->enableAuto();
        Notification::fake();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertRedirect();

        $this->assertDatabaseHas('taxi_dispatch_offers', [
            'taxi_booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'status' => TaxiDispatchOfferStatus::Pending->value,
        ]);
        Notification::assertSentTo($driver->user, CrmNotification::class);
    }

    public function test_vendor_can_start_own_booking(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($vendor)
            ->post(route('vendor.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertRedirect();

        $this->assertDatabaseHas('taxi_dispatch_offers', [
            'taxi_booking_id' => $booking->id,
            'driver_id' => $driver->id,
        ]);
    }

    public function test_cross_vendor_start_rejected(): void
    {
        $this->enableAuto();

        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)
            ->post(route('vendor.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertNotFound();

        $this->assertDatabaseCount('taxi_dispatch_offers', 0);
    }

    public function test_best_eligible_candidate_receives_first_offer(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $near = $this->makeDriver($vendor->vendorProfile);
        $far = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($near, 27.1760000, 78.0430000);
        $this->ping($far, 27.5000000, 78.5000000);

        $offer = $this->startOffer($booking, $this->makeAdmin());

        $this->assertSame($near->id, $offer->driver_id);
        $this->assertSame(1, $offer->rank);
    }

    public function test_ineligible_driver_never_receives_offer(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile, ['is_active' => false]);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driver, 27.1760000, 78.0430000);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertSessionHasErrors('auto_dispatch');

        $this->assertDatabaseCount('taxi_dispatch_offers', 0);
    }

    public function test_offer_belongs_to_exact_driver_and_vehicle(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $offer = $this->startOffer($booking);

        $this->assertSame($driver->id, $offer->driver_id);
        $this->assertSame($vehicle->id, $offer->vehicle_id);
        $this->assertNull($offer->taxi_assignment_id);
    }

    public function test_driver_sees_own_pending_offer(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->startOffer($booking);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.offers.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Driver/Taxi/Offers/Index')
                ->has('pending', 1)
                ->where('pending.0.booking.reference', $booking->reference));
    }

    public function test_driver_cannot_see_another_drivers_offer(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driverA = $this->makeDriver($vendor->vendorProfile);
        $driverB = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);
        $this->assertSame($driverA->id, $offer->driver_id);

        $this->actingAs($driverB->user)
            ->get(route('driver.taxi.offers.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('pending', 0));

        $this->actingAs($driverB->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertNotFound();
    }

    public function test_driver_can_accept_valid_offer(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertRedirect();

        $this->assertSame(TaxiDispatchOfferStatus::Accepted->value, $offer->fresh()->status);
        $this->assertSame($driver->id, $booking->fresh()->assigned_driver_id);
        $this->assertSame(TaxiBookingStatus::DriverAssigned->value, $booking->fresh()->status);
    }

    public function test_acceptance_creates_assignment(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertRedirect();

        $this->assertDatabaseHas('taxi_assignments', [
            'taxi_booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'unassigned_at' => null,
        ]);
        $this->assertDatabaseHas('taxi_booking_status_histories', [
            'taxi_booking_id' => $booking->id,
            'to_status' => TaxiBookingStatus::DriverAssigned->value,
        ]);
        $this->assertNotNull($offer->fresh()->taxi_assignment_id);
    }

    public function test_assignment_revalidated_on_acceptance(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $vehicle->update(['status' => 'maintenance']);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertSessionHasErrors('vehicle_id');

        $this->assertSame(TaxiDispatchOfferStatus::Pending->value, $offer->fresh()->status);
        $this->assertNull($booking->fresh()->assigned_driver_id);
    }

    public function test_conflict_after_offer_rejects_acceptance(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        app(TaxiBookingService::class)->assign($this->bookingFor($vendor->vendorProfile), $driver, $vehicle);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertSessionHasErrors('assignment');

        $this->assertNull($booking->fresh()->assigned_driver_id);
    }

    public function test_expired_offer_cannot_be_accepted(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);
        $offer->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertSessionHasErrors('offer');

        $this->assertNull($booking->fresh()->assigned_driver_id);
    }

    public function test_rejected_offer_moves_to_next_candidate(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driverA = $this->makeDriver($vendor->vendorProfile);
        $driverB = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driverA, 27.1760000, 78.0430000);
        $this->ping($driverB, 27.1800000, 78.0500000);
        $offerA = $this->startOffer($booking);
        $this->assertSame($driverA->id, $offerA->driver_id);

        $this->actingAs($driverA->user)
            ->post(route('driver.taxi.offers.reject', $offerA, absolute: false), ['reason' => 'too_far'])
            ->assertRedirect();

        $this->assertSame(TaxiDispatchOfferStatus::Rejected->value, $offerA->fresh()->status);

        $offerB = TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->firstOrFail();
        $this->assertSame($driverB->id, $offerB->driver_id);
    }

    public function test_expired_offer_moves_to_next_candidate(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driverA = $this->makeDriver($vendor->vendorProfile);
        $driverB = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driverA, 27.1760000, 78.0430000);
        $this->ping($driverB, 27.1800000, 78.0500000);
        $offerA = $this->startOffer($booking);
        $offerA->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('taxi:expire-dispatch-offers')->assertExitCode(0);

        $this->assertSame(TaxiDispatchOfferStatus::Expired->value, $offerA->fresh()->status);

        $offerB = TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->firstOrFail();
        $this->assertSame($driverB->id, $offerB->driver_id);
    }

    public function test_same_driver_not_reoffered_in_same_cycle(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driverA = $this->makeDriver($vendor->vendorProfile);
        $driverB = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($driverA, 27.1760000, 78.0430000);
        $this->ping($driverB, 27.1800000, 78.0500000);
        $offerA = $this->startOffer($booking);

        $this->actingAs($driverA->user)
            ->post(route('driver.taxi.offers.reject', $offerA, absolute: false))
            ->assertRedirect();

        $offerB = TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->firstOrFail();

        $this->actingAs($driverB->user)
            ->post(route('driver.taxi.offers.reject', $offerB, absolute: false))
            ->assertRedirect();

        $this->assertSame(1, TaxiDispatchOffer::where('taxi_booking_id', $booking->id)->where('driver_id', $driverA->id)->count());
        $this->assertNull(TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->first());
    }

    public function test_max_attempts_respected(): void
    {
        $this->enableAuto(['taxi.dispatch.max_offer_attempts' => '1']);

        $vendor = $this->makeVendor();
        $driverA = $this->makeDriver($vendor->vendorProfile);
        $driverB = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offerA = $this->startOffer($booking);

        $this->actingAs($driverA->user)
            ->post(route('driver.taxi.offers.reject', $offerA, absolute: false))
            ->assertRedirect();

        $this->assertNull(TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->first());
        $this->assertSame(1, TaxiDispatchOffer::where('taxi_booking_id', $booking->id)->attempted()->count());
    }

    public function test_candidate_exhaustion_falls_back_to_manual(): void
    {
        $this->enableAuto(['taxi.dispatch.max_offer_attempts' => '1']);

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.reject', $offer, absolute: false))
            ->assertRedirect();

        $this->assertSame('exhausted', app(TaxiAutoDispatchService::class)->statusFor($booking->fresh()));
        $this->assertSame(TaxiBookingStatus::Confirmed->value, $booking->fresh()->status);
        $this->assertNull($booking->fresh()->assigned_driver_id);
    }

    public function test_manual_assignment_cancels_pending_offers(): void
    {
        $this->enableAuto();
        Notification::fake();

        $vendor = $this->makeVendor();
        $driverA = $this->makeDriver($vendor->vendorProfile);
        $driverB = $this->makeDriver($vendor->vendorProfile);
        $vehicleB = $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.assign', $booking, absolute: false), [
                'driver_id' => $driverB->id,
                'vehicle_id' => $vehicleB->id,
            ])
            ->assertRedirect();

        $this->assertSame(TaxiDispatchOfferStatus::Cancelled->value, $offer->fresh()->status);
        $this->assertSame($driverB->id, $booking->fresh()->assigned_driver_id);
    }

    public function test_second_late_acceptance_blocked(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertRedirect();

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertSessionHasErrors('offer');

        $this->assertSame(1, TaxiAssignment::where('taxi_booking_id', $booking->id)->whereNull('unassigned_at')->count());
    }

    public function test_assigned_booking_cannot_start(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $vehicle = $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertSessionHasErrors('auto_dispatch');
    }

    public function test_cancelled_booking_cancels_offers(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $this->actingAs($this->makeAdmin())
            ->patch(route('admin.taxi.bookings.status', $booking, absolute: false), ['status' => TaxiBookingStatus::Cancelled->value])
            ->assertRedirect();

        $this->assertSame(TaxiDispatchOfferStatus::Cancelled->value, $offer->fresh()->status);
    }

    public function test_module_disabled_blocks_auto_dispatch(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertNotFound();

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.offers.index', absolute: false))
            ->assertNotFound();
    }

    public function test_vendor_only_sees_own_offer_history(): void
    {
        $this->enableAuto();

        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $driver = $this->makeDriver($vendorA->vendorProfile);
        $this->makeVehicle($vendorA->vendorProfile);
        $booking = $this->bookingFor($vendorA->vendorProfile);
        $this->startOffer($booking);

        $this->actingAs($vendorA)
            ->get(route('vendor.taxi.bookings.show', $booking, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vendor/Taxi/Bookings/Show')
                ->has('autoDispatch.history', 1));

        $this->actingAs($vendorB)
            ->get(route('vendor.taxi.bookings.show', $booking, absolute: false))
            ->assertNotFound();
    }

    public function test_notifications_only_at_correct_stages(): void
    {
        $this->enableAuto();
        Notification::fake();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->startOffer($booking);

        Notification::assertSentTo($driver->user, CrmNotification::class);
        Notification::assertNotSentTo($vendor, CrmNotification::class);
    }

    public function test_customer_not_notified_on_offer_creation(): void
    {
        $this->enableAuto();
        Notification::fake();

        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id]);
        $this->startOffer($booking);

        Notification::assertNotSentTo($customer, CrmNotification::class);
    }

    public function test_assignment_notification_after_accepted_offer(): void
    {
        $this->enableAuto();
        Notification::fake();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertRedirect();

        Notification::assertSentTo(
            $driver->user,
            CrmNotification::class,
            fn (CrmNotification $notification): bool => $notification->kind === 'taxi_assignment',
        );
    }

    public function test_scheduler_expiry_is_idempotent(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->startOffer($booking);
        $offer->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('taxi:expire-dispatch-offers')->assertExitCode(0);
        $this->artisan('taxi:expire-dispatch-offers')->assertExitCode(0);

        $this->assertSame(1, TaxiDispatchOffer::where('taxi_booking_id', $booking->id)->where('status', TaxiDispatchOfferStatus::Expired->value)->count());
        $this->assertSame(1, TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->count());
    }

    public function test_repeated_start_does_not_duplicate_offers(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->post(route('admin.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.taxi.bookings.auto-dispatch.start', $booking, absolute: false))
            ->assertRedirect();

        $this->assertSame(1, TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->count());
    }

    public function test_pricing_snapshot_never_changes(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $driver = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $booking->forceFill([
            'total_amount' => '65.00',
            'pricing_snapshot' => ['version' => 1, 'grand_total' => '65.00'],
        ])->save();
        $offer = $this->startOffer($booking);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.offers.accept', $offer, absolute: false))
            ->assertRedirect();

        $this->assertSame('65.00', $booking->fresh()->total_amount);
        $this->assertSame(['version' => 1, 'grand_total' => '65.00'], $booking->fresh()->pricing_snapshot);
    }

    public function test_ranking_service_is_reused(): void
    {
        $this->enableAuto();

        $vendor = $this->makeVendor();
        $near = $this->makeDriver($vendor->vendorProfile);
        $far = $this->makeDriver($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $this->makeVehicle($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->ping($near, 27.1760000, 78.0430000);
        $this->ping($far, 27.5000000, 78.5000000);

        $top = app(TaxiDispatchRecommendationService::class)->recommend($booking)['recommendations'][0];
        $offer = $this->startOffer($booking);

        $this->assertSame($top['driver_id'], $offer->driver_id);
        $this->assertSame($top['vehicle_id'], $offer->vehicle_id);
        $this->assertSame($top['rank'], $offer->rank);
    }
}
