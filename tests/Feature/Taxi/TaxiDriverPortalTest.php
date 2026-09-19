<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingNote;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use App\Services\TaxiBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 12A.4 driver portal (own assigned trips only).
 *
 * Run ONE method at a time inside the OS memory cage — never the whole
 * file (see Phase 12A.3 OOM history).
 */
class TaxiDriverPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
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

    protected function fleetFor(VendorProfile $profile): array
    {
        $vehicle = Vehicle::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => 'available',
            'is_active' => true,
            'passenger_capacity' => 4,
        ]);

        return [$vehicle];
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

    protected function assignTrip(TaxiBooking $booking, Driver $driver, Vehicle $vehicle): TaxiAssignment
    {
        return app(TaxiBookingService::class)->assign($booking, $driver, $vehicle);
    }

    public function test_driver_dashboard_renders(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $this->assignTrip($this->bookingFor($profile), $driver, $vehicle);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.dashboard', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Driver/Taxi/Dashboard')
                ->has('driver')
                ->has('metrics')
                ->has('todayTrips', 1)
                ->has('upcomingTrips'));
    }

    public function test_driver_only_sees_own_assigned_trips(): void
    {
        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $this->assignTrip($this->bookingFor($profile, ['reference' => 'TX-DRV-A-001']), $driverA, $vehicle);

        $this->actingAs($driverB->user)
            ->get(route('driver.taxi.trips.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Driver/Taxi/Trips/Index')
                ->has('trips.data', 0));

        $this->actingAs($driverA->user)
            ->get(route('driver.taxi.trips.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('trips.data', 1)
                ->where('trips.data.0.reference', 'TX-DRV-A-001'));
    }

    public function test_driver_cannot_access_another_drivers_trip(): void
    {
        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driverA, $vehicle);

        $this->actingAs($driverB->user)
            ->get(route('driver.taxi.trips.show', $booking, absolute: false))
            ->assertNotFound();
    }

    public function test_unlinked_user_cannot_access_driver_portal(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->get(route('driver.taxi.dashboard', absolute: false))
            ->assertForbidden();
    }

    public function test_inactive_driver_access_blocked(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile, null, ['is_active' => false]);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.dashboard', absolute: false))
            ->assertForbidden();
    }

    public function test_assigned_trip_details_render_without_financials(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $vehicle);

        $response = $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.show', $booking, absolute: false));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Driver/Taxi/Trips/Show')
                ->where('booking.reference', $booking->reference)
                ->has('assignment')
                ->has('allowedTransitions'))
            ->assertDontSee('"total_amount"', false)
            ->assertDontSee('grand_total', false);
    }

    public function test_valid_driver_status_transition(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $vehicle);

        $this->actingAs($driver->user)
            ->patch(route('driver.taxi.trips.status', $booking, absolute: false), ['status' => TaxiBookingStatus::EnRoute->value])
            ->assertRedirect();

        $this->assertSame(TaxiBookingStatus::EnRoute->value, $booking->fresh()->status);
        $this->assertDatabaseHas('taxi_booking_status_histories', [
            'taxi_booking_id' => $booking->id,
            'to_status' => TaxiBookingStatus::EnRoute->value,
        ]);
    }

    public function test_invalid_transition_rejected(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $vehicle);

        $this->actingAs($driver->user)
            ->patch(route('driver.taxi.trips.status', $booking, absolute: false), ['status' => TaxiBookingStatus::Completed->value])
            ->assertSessionHasErrors('status');
    }

    public function test_driver_cannot_update_unassigned_booking(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        $booking = $this->bookingFor($profile);

        $this->actingAs($driver->user)
            ->patch(route('driver.taxi.trips.status', $booking, absolute: false), ['status' => TaxiBookingStatus::EnRoute->value])
            ->assertNotFound();

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.show', $booking, absolute: false))
            ->assertNotFound();
    }

    public function test_assignment_acknowledgement(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);
        $assignment = $this->assignTrip($booking, $driver, $vehicle);

        $this->assertNull($assignment->fresh()->acknowledged_at);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.trips.acknowledge', $booking, absolute: false))
            ->assertRedirect();

        $this->assertNotNull($assignment->fresh()->acknowledged_at);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.trips.acknowledge', $booking, absolute: false))
            ->assertRedirect();
    }

    public function test_availability_update_safe_values(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->patch(route('driver.taxi.profile.availability', absolute: false), ['availability_status' => 'offline'])
            ->assertRedirect();

        $this->assertSame('offline', $driver->fresh()->availability_status);
    }

    public function test_protected_availability_state_cannot_be_forged(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->patch(route('driver.taxi.profile.availability', absolute: false), ['availability_status' => 'on_leave'])
            ->assertSessionHasErrors('availability_status');

        $this->assertSame('available', $driver->fresh()->availability_status);
    }

    public function test_operational_note_creation(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $vehicle);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.trips.notes.store', $booking, absolute: false), ['body' => 'Delayed due to traffic'])
            ->assertRedirect();

        $this->assertDatabaseHas('taxi_booking_notes', [
            'taxi_booking_id' => $booking->id,
            'body' => 'Delayed due to traffic',
            'visible_to_driver' => true,
        ]);
    }

    public function test_internal_only_note_hidden(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);
        $this->assignTrip($booking, $driver, $vehicle);

        TaxiBookingNote::create([
            'taxi_booking_id' => $booking->id,
            'body' => 'Internal vendor margin discussion',
            'visible_to_driver' => false,
        ]);

        $this->actingAs($driver->user)
            ->post(route('driver.taxi.trips.notes.store', $booking, absolute: false), ['body' => 'Customer not reachable'])
            ->assertRedirect();

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.notes.index', $booking, absolute: false))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Customer not reachable'])
            ->assertJsonMissing(['body' => 'Internal vendor margin discussion']);
    }

    public function test_cancellation_and_reassignment_notification(): void
    {
        Notification::fake();

        $profile = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profile);
        $driverB = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile);

        app(TaxiBookingService::class)->assign($booking, $driverA, $vehicle);

        $admin = User::factory()->create(['role' => 'admin']);
        app(TaxiBookingService::class)->changeStatus($booking->fresh(), TaxiBookingStatus::Cancelled, $admin);

        Notification::assertSentTo($driverA->user, CrmNotification::class);

        $booking2 = $this->bookingFor($profile);
        [$vehicle2] = $this->fleetFor($profile);
        app(TaxiBookingService::class)->assign($booking2, $driverB, $vehicle2);

        Notification::assertSentTo($driverB->user, CrmNotification::class);
    }

    public function test_completed_trip_history(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);
        [$vehicle] = $this->fleetFor($profile);
        $booking = $this->bookingFor($profile, ['reference' => 'TX-DRV-HIST-001']);
        $this->assignTrip($booking, $driver, $vehicle);

        $service = app(TaxiBookingService::class);
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::EnRoute);
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::Arrived);
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::PassengerOnBoard);
        $service->changeStatus($booking->fresh(), TaxiBookingStatus::Completed);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.index', ['view' => 'history'], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Driver/Taxi/Trips/Index')
                ->has('trips.data', 1)
                ->where('trips.data.0.reference', 'TX-DRV-HIST-001'));
    }

    public function test_module_disabled_blocks_driver_portal(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.dashboard', absolute: false))
            ->assertNotFound();
    }

    public function test_vendor_isolation_between_drivers(): void
    {
        $profileA = VendorProfile::factory()->create();
        $profileB = VendorProfile::factory()->create();
        $driverA = $this->makeDriver($profileA);
        $driverB = $this->makeDriver($profileB);
        [$vehicleA] = $this->fleetFor($profileA);
        $bookingA = $this->bookingFor($profileA);
        $this->assignTrip($bookingA, $driverA, $vehicleA);

        $this->actingAs($driverB->user)
            ->get(route('driver.taxi.trips.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('trips.data', 0));

        $this->actingAs($driverB->user)
            ->get(route('driver.taxi.trips.show', $bookingA, absolute: false))
            ->assertNotFound();
    }

    public function test_tampered_booking_id_rejected(): void
    {
        $profile = VendorProfile::factory()->create();
        $driver = $this->makeDriver($profile);

        $this->actingAs($driver->user)
            ->get(route('driver.taxi.trips.show', 999999, absolute: false))
            ->assertNotFound();

        $this->actingAs($driver->user)
            ->patch(route('driver.taxi.trips.status', 999999, absolute: false), ['status' => TaxiBookingStatus::EnRoute->value])
            ->assertNotFound();
    }
}
