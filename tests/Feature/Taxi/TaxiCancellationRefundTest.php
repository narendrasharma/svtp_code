<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Enums\TaxiDispatchOfferStatus;
use App\Http\Controllers\TaxiTrackingController;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingCancellation;
use App\Models\TaxiCancellationPolicy;
use App\Models\TaxiDispatchOffer;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiRefund;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use App\Services\TaxiAutoDispatchService;
use App\Services\TaxiBookingService;
use App\Services\TaxiCancellationService;
use App\Services\TaxiRefundService;
use App\Services\TaxiRescheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiCancellationRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    // ---- helpers ----------------------------------------------------

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

    protected function makeCustomer(): User
    {
        return User::factory()->create(['role' => 'customer']);
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
            'vehicle_type_id' => VehicleType::factory()->create()->id,
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
            'pickup_at' => now()->addDay(),
            'passenger_count' => 2,
            'currency' => 'INR',
            'total_amount' => 2500,
        ], $overrides));
    }

    protected function pay(TaxiBooking $booking, float $amount, string $currency = 'INR'): void
    {
        $booking->payments()->create([
            'reference' => $booking->reference.'-P'.Str::upper(Str::random(4)),
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);
    }

    protected function policy(array $overrides = []): TaxiCancellationPolicy
    {
        return TaxiCancellationPolicy::create(array_merge([
            'name' => 'Test policy',
            'currency' => 'INR',
            'is_active' => true,
            'fee_type' => 'fixed',
            'fee_value' => 0,
            'no_show_fee_type' => 'fixed',
            'no_show_fee_value' => 0,
            'minimum_fee' => 0,
        ], $overrides));
    }

    protected function cancel(TaxiBooking $booking, ?User $actor = null, string $actorType = 'admin', string $reason = 'customer_request'): TaxiBookingCancellation
    {
        return app(TaxiCancellationService::class)->cancel(
            $booking, $reason, 'Cancellation note', $actor ?? $this->makeAdmin(), $actorType
        );
    }

    protected function pendingOffer(TaxiBooking $booking, Driver $driver, Vehicle $vehicle): TaxiDispatchOffer
    {
        return TaxiDispatchOffer::create([
            'taxi_booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'rank' => 1,
            'status' => TaxiDispatchOfferStatus::Pending->value,
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'source' => 'manual',
        ]);
    }

    // ---- 1 cancellation disabled ------------------------------------

    public function test_cancellation_disabled_blocks_quote_and_cancel(): void
    {
        Setting::setValue('taxi.cancellation.enabled', '0');
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        try {
            app(TaxiCancellationService::class)->quote($booking);
            $this->fail('Quote should be blocked when cancellation is disabled.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }

        try {
            $this->cancel($booking);
            $this->fail('Cancel should be blocked when cancellation is disabled.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }
    }

    // ---- 2 cancellation quote ---------------------------------------

    public function test_cancellation_quote_returns_normalized_data(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 1000);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('2500.00', $quote['booking_total']);
        $this->assertSame('1000.00', $quote['amount_paid']);
        $this->assertArrayHasKey('cancellation_fee', $quote);
        $this->assertArrayHasKey('refundable_amount', $quote);
        $this->assertArrayHasKey('non_refundable_amount', $quote);
        $this->assertArrayHasKey('cutoff_state', $quote);
        $this->assertArrayHasKey('calculated_at', $quote);
        $this->assertSame('INR', $quote['currency']);
        $this->assertSame(1, $quote['version']);
    }

    // ---- 3 free cutoff ----------------------------------------------

    public function test_free_cutoff_yields_zero_fee(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addDays(3)]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500, 'free_cancel_before_minutes' => 60]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('0.00', $quote['cancellation_fee']);
        $this->assertSame('free', $quote['cutoff_state']);
        $this->assertSame('2500.00', $quote['refundable_amount']);
    }

    // ---- 4 fixed fee -------------------------------------------------

    public function test_fixed_fee_applies(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('500.00', $quote['cancellation_fee']);
        $this->assertSame('2000.00', $quote['refundable_amount']);
        $this->assertSame('fee_window', $quote['cutoff_state']);
    }

    // ---- 5 percentage fee --------------------------------------------

    public function test_percentage_fee_applies_to_booking_total(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'percentage', 'fee_value' => 10]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('250.00', $quote['cancellation_fee']);
        $this->assertSame('2250.00', $quote['refundable_amount']);
    }

    // ---- 6 minimum fee -----------------------------------------------

    public function test_minimum_fee_raises_small_fee(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 50, 'minimum_fee' => 200]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('200.00', $quote['cancellation_fee']);
    }

    // ---- 7 maximum fee -----------------------------------------------

    public function test_maximum_fee_caps_large_fee(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'percentage', 'fee_value' => 50, 'minimum_fee' => 0, 'maximum_fee' => 300]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('300.00', $quote['cancellation_fee']);
    }

    // ---- 8 non-refundable window -------------------------------------

    public function test_non_refundable_window_takes_full_total_as_fee(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'non_refundable', 'fee_value' => 0]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('2500.00', $quote['cancellation_fee']);
        $this->assertSame('0.00', $quote['refundable_amount']);
    }

    // ---- 9 vendor policy precedence ----------------------------------

    public function test_vendor_policy_beats_platform_policy(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['name' => 'Platform', 'vendor_profile_id' => null, 'fee_type' => 'fixed', 'fee_value' => 900]);
        $this->policy(['name' => 'Vendor', 'vendor_profile_id' => $vendor->vendorProfile->id, 'fee_type' => 'fixed', 'fee_value' => 100]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('100.00', $quote['cancellation_fee']);
        $this->assertSame('Vendor', $quote['policy']['name']);
    }

    // ---- 10 platform fallback ----------------------------------------

    public function test_platform_policy_used_when_no_vendor_policy(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['name' => 'Platform fallback', 'vendor_profile_id' => null, 'fee_type' => 'fixed', 'fee_value' => 700]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('700.00', $quote['cancellation_fee']);
    }

    // ---- 11 inactive/expired ignored ---------------------------------

    public function test_inactive_and_expired_policies_are_ignored(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['name' => 'Inactive', 'is_active' => false, 'fee_type' => 'fixed', 'fee_value' => 900]);
        $this->policy(['name' => 'Expired', 'fee_type' => 'fixed', 'fee_value' => 800, 'effective_until' => now()->subDay()]);

        $quote = app(TaxiCancellationService::class)->quote($booking);

        $this->assertSame('0.00', $quote['cancellation_fee']);
        $this->assertNull($quote['policy']);
        $this->assertSame('2500.00', $quote['refundable_amount']);
    }

    // ---- 12 forged client fee/refund ignored --------------------------

    public function test_forged_client_fee_is_ignored_on_cancel(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);

        $response = $this->actingAs($admin)->post(route('admin.taxi.changes.cancel', $booking, absolute: false), [
            'reason_code' => 'customer_request',
            'reason' => 'Forged attempt',
            'confirmed' => true,
            'cancellation_fee' => '0.00',
            'refundable_amount' => '2500.00',
        ]);

        $response->assertRedirect();

        $cancellation = TaxiBookingCancellation::where('taxi_booking_id', $booking->id)->firstOrFail();
        $this->assertSame('500.00', (string) $cancellation->cancellation_fee);
        $this->assertSame('2000.00', (string) $cancellation->refundable_amount);
    }

    // ---- 13 customer own cancellation ---------------------------------

    public function test_customer_can_cancel_own_booking(): void
    {
        Setting::setValue('taxi.customer_cancellation.enabled', '1');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id]);
        $this->pay($booking, 2500);

        $response = $this->actingAs($customer)->post(route('account.taxi.changes.cancel', $booking, absolute: false), [
            'reason_code' => 'customer_request',
            'reason' => 'Plans changed',
            'confirmed' => true,
        ]);

        $response->assertRedirect();
        $this->assertSame(TaxiBookingStatus::Cancelled->value, $booking->fresh()->status);
        $this->assertDatabaseHas('taxi_booking_cancellations', [
            'taxi_booking_id' => $booking->id,
            'requested_by_type' => 'customer',
        ]);
    }

    // ---- 14 foreign customer blocked -----------------------------------

    public function test_foreign_customer_cannot_cancel(): void
    {
        Setting::setValue('taxi.customer_cancellation.enabled', '1');
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->post(route('account.taxi.changes.cancel', $booking, absolute: false), [
            'reason_code' => 'customer_request',
            'reason' => 'Hijack',
            'confirmed' => true,
        ]);

        $response->assertNotFound();
        $this->assertSame(TaxiBookingStatus::Confirmed->value, $booking->fresh()->status);
    }

    // ---- 15 unauthorized access blocked --------------------------------

    public function test_guest_cannot_reach_change_endpoints(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $this->getJson(route('admin.taxi.changes.quote', $booking, absolute: false))->assertUnauthorized();
        $this->getJson(route('account.taxi.changes.quote', $booking, absolute: false))->assertUnauthorized();
    }

    // ---- 16 vendor own cancellation ------------------------------------

    public function test_vendor_can_cancel_own_booking(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);

        $response = $this->actingAs($vendor)->post(route('vendor.taxi.changes.cancel', $booking, absolute: false), [
            'reason_code' => 'operational_issue',
            'reason' => 'Vehicle broke down',
            'confirmed' => true,
        ]);

        $response->assertRedirect();
        $this->assertSame(TaxiBookingStatus::Cancelled->value, $booking->fresh()->status);
    }

    // ---- 17 foreign vendor blocked --------------------------------------

    public function test_foreign_vendor_cannot_cancel(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $response = $this->actingAs($vendorB)->post(route('vendor.taxi.changes.cancel', $booking, absolute: false), [
            'reason_code' => 'operational_issue',
            'reason' => 'Hijack',
            'confirmed' => true,
        ]);

        $response->assertNotFound();
        $this->assertSame(TaxiBookingStatus::Confirmed->value, $booking->fresh()->status);
    }

    // ---- 18 admin cancellation ------------------------------------------

    public function test_admin_can_cancel_booking(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 250]);

        $response = $this->actingAs($admin)->post(route('admin.taxi.changes.cancel', $booking, absolute: false), [
            'reason_code' => 'customer_request',
            'reason' => 'Customer called support',
            'confirmed' => true,
        ]);

        $response->assertRedirect();

        $fresh = $booking->fresh();
        $this->assertSame(TaxiBookingStatus::Cancelled->value, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $cancellation = TaxiBookingCancellation::where('taxi_booking_id', $booking->id)->firstOrFail();
        $this->assertSame('250.00', (string) $cancellation->cancellation_fee);
        $this->assertSame($admin->id, (int) $cancellation->cancelled_by);
    }

    // ---- 19 completed blocked --------------------------------------------

    public function test_completed_booking_cannot_be_cancelled(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['status' => TaxiBookingStatus::Completed->value]);

        try {
            $this->cancel($booking);
            $this->fail('Completed bookings must refuse cancellation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }

        $this->assertSame(TaxiBookingStatus::Completed->value, $booking->fresh()->status);
    }

    // ---- 20 passenger_on_board restricted ---------------------------------

    public function test_passenger_on_board_booking_cannot_be_cancelled(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['status' => TaxiBookingStatus::PassengerOnBoard->value]);

        try {
            $this->cancel($booking);
            $this->fail('On-board bookings must refuse cancellation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }
    }

    // ---- 21 repeated cancellation idempotent -------------------------------

    public function test_repeated_cancellation_returns_same_record(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        $first = $this->cancel($booking, $admin);
        $second = $this->cancel($booking, $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TaxiBookingCancellation::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 22 active assignment cleaned -----------------------------------------

    public function test_cancellation_closes_active_assignment(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle, $admin);
        $this->assertSame(TaxiBookingStatus::DriverAssigned->value, $booking->fresh()->status);

        $this->cancel($booking, $admin);

        $fresh = $booking->fresh();
        $this->assertNull($fresh->assigned_driver_id);
        $this->assertNull($fresh->assigned_vehicle_id);
        $this->assertNotNull($fresh->assignments()->first()->unassigned_at);
    }

    // ---- 23 pending offers cancelled ---------------------------------------------

    public function test_cancellation_cancels_pending_offers(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->pendingOffer($booking, $driver, $vehicle);

        $this->cancel($booking, $admin);

        $this->assertSame(TaxiDispatchOfferStatus::Cancelled->value, $offer->fresh()->status);
    }

    // ---- 24 customer live tracking stopped ------------------------------------------

    public function test_cancelled_booking_hides_live_tracking(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile, ['status' => TaxiBookingStatus::EnRoute->value]);

        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle, $admin);
        // Assignment moves confirmed bookings only; force the trip state here.
        $booking->forceFill(['status' => TaxiBookingStatus::EnRoute->value])->save();
        $this->cancel($booking->fresh(), $admin);

        $fresh = $booking->fresh();
        $this->assertNull($fresh->assigned_driver_id);
        $this->assertFalse(in_array($fresh->status, TaxiTrackingController::visibleStatuses(), true));
        $this->assertTrue($fresh->status()->isTerminal());
    }

    // ---- 25 driver cancellation notification -------------------------------------------

    public function test_driver_is_notified_on_cancellation(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $driverUser = User::factory()->create(['role' => 'customer']);
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile, ['user_id' => $driverUser->id]);
        $booking = $this->bookingFor($vendor->vendorProfile);

        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle, $admin);
        Notification::fake();
        $this->cancel($booking->fresh(), $admin);

        Notification::assertSentTo($driverUser, CrmNotification::class);
    }

    // ---- 26 customer cancellation notification --------------------------------------------

    public function test_customer_is_notified_on_cancellation(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id]);

        $this->cancel($booking, $admin);

        Notification::assertSentTo($customer, CrmNotification::class);
    }

    // ---- 27 cancellation snapshot immutable -------------------------------------------------

    public function test_cancellation_snapshot_is_immutable(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $cancellation = $this->cancel($booking);

        try {
            $cancellation->update(['reason_text' => 'tampered']);
            $this->fail('Cancellation rows must be immutable.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        try {
            $cancellation->delete();
            $this->fail('Cancellation rows must not be deletable.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
    }

    // ---- 28 refund limited to paid amount ------------------------------------------------------

    public function test_refund_cannot_exceed_amount_paid(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 1000);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 200]);
        $this->cancel($booking, $admin);

        try {
            app(TaxiRefundService::class)->create($booking->fresh(), '900.00', (string) Str::uuid(), 'Too much', $admin);
            $this->fail('Refund above the paid balance must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    // ---- 29 refund respects cancellation fee -------------------------------------------------------

    public function test_refund_respects_cancellation_fee(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);
        $this->cancel($booking, $admin);

        $refund = app(TaxiRefundService::class)->create($booking->fresh(), '2000.00', (string) Str::uuid(), 'Entitlement', $admin);

        $this->assertSame('2000.00', (string) $refund->amount);
        $this->assertCount(1, $refund->items);
    }

    // ---- 30 prior refund reduces refundable ----------------------------------------------------------------

    public function test_prior_refund_reduces_remaining_balance(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);
        $this->cancel($booking, $admin);

        app(TaxiRefundService::class)->create($booking->fresh(), '1200.00', (string) Str::uuid(), 'First part', $admin);

        $summary = app(TaxiRefundService::class)->summary($booking->fresh());
        $this->assertSame(800.0, $summary['refundable_remaining']);

        try {
            app(TaxiRefundService::class)->create($booking->fresh(), '900.00', (string) Str::uuid(), 'Too much now', $admin);
            $this->fail('Second refund above the remainder must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    // ---- 31 over-refund rejected ---------------------------------------------------------------------------------

    public function test_over_refund_is_rejected_without_cancellation(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);

        try {
            app(TaxiRefundService::class)->create($booking, '100.00', (string) Str::uuid(), 'No cancellation yet', $admin);
            $this->fail('Refunds require a cancellation record.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    // ---- 32 currency mismatch rejected -------------------------------------------------------------------------------

    public function test_currency_mismatch_blocks_quote_and_refund(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['currency' => 'INR']);
        $this->pay($booking, 500, 'USD');

        try {
            app(TaxiCancellationService::class)->quote($booking);
            $this->fail('Mixed-currency payments must block the quote.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('currency', $e->errors());
        }

        $this->policy(['fee_type' => 'fixed', 'fee_value' => 0]);
        $booking->payments()->delete();
        $this->pay($booking, 1000, 'INR');
        $this->cancel($booking, $admin);

        $booking->payments()->create([
            'reference' => $booking->reference.'-PX'.Str::upper(Str::random(4)),
            'amount' => '100.00',
            'currency' => 'USD',
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        try {
            app(TaxiRefundService::class)->create($booking->fresh(), '100.00', (string) Str::uuid(), 'Mismatch', $admin);
            $this->fail('Mixed-currency payments must block refunds.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('currency', $e->errors());
        }
    }

    // ---- 33 manual refund reference persisted ------------------------------------------------------------------------------

    public function test_mark_refunded_persists_method_and_reference(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);
        $this->cancel($booking, $admin);

        $refund = app(TaxiRefundService::class)->create($booking->fresh(), '1000.00', (string) Str::uuid(), 'Bank return', $admin);
        $processed = app(TaxiRefundService::class)->process($refund, 'bank_transfer', 'UTR-12345', $admin);

        $this->assertSame('processed', $processed->status);
        $this->assertSame('bank_transfer', $processed->method);
        $this->assertSame('UTR-12345', $processed->reference);
        $this->assertNotNull($processed->refunded_at);
        $this->assertStringStartsWith('TXR-', $processed->refund_number);
    }

    // ---- 34 repeated mark-refunded idempotent ------------------------------------------------------------------------------------

    public function test_repeated_mark_refunded_is_idempotent(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);
        $this->cancel($booking, $admin);

        $refund = app(TaxiRefundService::class)->create($booking->fresh(), '1000.00', (string) Str::uuid(), 'Bank return', $admin);
        $first = app(TaxiRefundService::class)->process($refund, 'bank_transfer', 'UTR-12345', $admin);
        $second = app(TaxiRefundService::class)->process($refund->fresh(), 'bank_transfer', 'UTR-12345', $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TaxiRefund::where('taxi_booking_id', $booking->id)->count());
        $summary = app(TaxiRefundService::class)->summary($booking->fresh());
        $this->assertSame(1000.0, $summary['refunded']);
    }

    // ---- 35 multiple refunds cannot exceed payment --------------------------------------------------------------------------------------

    public function test_multiple_partial_refunds_cannot_exceed_payment(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 1500);
        $this->pay($booking, 1000);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);
        $this->cancel($booking, $admin);

        app(TaxiRefundService::class)->create($booking->fresh(), '1200.00', (string) Str::uuid(), 'Part one', $admin);
        app(TaxiRefundService::class)->create($booking->fresh(), '800.00', (string) Str::uuid(), 'Part two', $admin);

        $summary = app(TaxiRefundService::class)->summary($booking->fresh());
        $this->assertEquals(0, $summary['refundable_remaining']);

        try {
            app(TaxiRefundService::class)->create($booking->fresh(), '0.01', (string) Str::uuid(), 'One paisa too many', $admin);
            $this->fail('Refunds must never exceed the payment-backed balance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    // ---- 36 vendor foreign refund blocked -------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_refund_foreign_booking(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->bookingFor($vendorA->vendorProfile);

        $response = $this->actingAs($vendorB)->post(route('vendor.taxi.changes.refunds.store', $booking, absolute: false), [
            'amount' => '100.00',
            'request_key' => (string) Str::uuid(),
            'reason' => 'Hijack refund',
        ]);

        $response->assertNotFound();
        $this->assertSame(0, TaxiRefund::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 37 driver refund admin endpoint blocked -----------------------------------------------------------------------------------------------

    public function test_driver_identity_cannot_use_refund_endpoints(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 0]);
        $this->cancel($booking, $admin);

        $driverUser = User::factory()->create(['role' => 'customer']);
        Driver::factory()->create(['vendor_profile_id' => $vendor->vendorProfile->id, 'user_id' => $driverUser->id]);

        $response = $this->actingAs($driverUser)->post(route('admin.taxi.changes.refunds.store', $booking, absolute: false), [
            'amount' => '100.00',
            'request_key' => (string) Str::uuid(),
            'reason' => 'Driver refund attempt',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, TaxiRefund::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 38 refund-aware payment summary -----------------------------------------------------------------------------------------------------------------

    public function test_payment_summary_is_refund_aware(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);
        $this->cancel($booking, $admin);

        $refund = app(TaxiRefundService::class)->create($booking->fresh(), '1000.00', (string) Str::uuid(), 'Part', $admin);
        app(TaxiRefundService::class)->process($refund, 'upi', 'UPI-999', $admin);

        $summary = app(TaxiBookingService::class)->summary($booking->fresh());

        $this->assertSame(2500.0, $summary['paid']);
        $this->assertSame(1000.0, $summary['refunded']);
        $this->assertSame(1500.0, $summary['net_paid']);
        $this->assertSame(1000.0, $summary['refundable_remaining']);
    }

    // ---- 39 eligible reschedule -----------------------------------------------------------------------------------------------------------------------------------

    public function test_eligible_reschedule_moves_pickup_and_records_history(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addDay()]);
        $oldPickup = $booking->pickup_at->copy();
        $newPickup = now()->addDays(2)->startOfMinute();

        $history = app(TaxiRescheduleService::class)->reschedule(
            $booking, $newPickup->format('Y-m-d H:i:s'), null, 'Customer asked for a later pickup', $admin
        );

        $this->assertNotNull($history);
        $this->assertTrue($oldPickup->equalTo($history->old_pickup_at));
        $this->assertTrue($newPickup->equalTo($history->new_pickup_at));
        $this->assertSame('Customer asked for a later pickup', $history->reason);
        $this->assertTrue($newPickup->equalTo($booking->fresh()->pickup_at));
    }

    // ---- 40 invalid/past reschedule rejected ----------------------------------------------------------------------------------------------------------------------------

    public function test_past_or_inverted_reschedule_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        try {
            app(TaxiRescheduleService::class)->reschedule($booking, now()->subHour()->format('Y-m-d H:i:s'), null, 'Past', $admin);
            $this->fail('Past pickups must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pickup_at', $e->errors());
        }

        $roundTrip = $this->bookingFor($vendor->vendorProfile, [
            'trip_type' => 'round_trip',
            'return_at' => now()->addDays(2),
        ]);

        try {
            app(TaxiRescheduleService::class)->reschedule(
                $roundTrip, now()->addDays(3)->format('Y-m-d H:i:s'), now()->addDays(2)->format('Y-m-d H:i:s'), 'Inverted', $admin
            );
            $this->fail('Return before pickup must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pickup_at', $e->errors());
        }
    }

    // ---- 41 reschedule history ---------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_reschedule_preserves_old_and_new_values(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $oldPickup = now()->addDay()->startOfMinute();
        $oldReturn = now()->addDays(2)->startOfMinute();
        $booking = $this->bookingFor($vendor->vendorProfile, [
            'trip_type' => 'round_trip',
            'pickup_at' => $oldPickup,
            'return_at' => $oldReturn,
        ]);
        $newPickup = now()->addDays(4)->startOfMinute();
        $newReturn = now()->addDays(5)->startOfMinute();

        $history = app(TaxiRescheduleService::class)->reschedule(
            $booking, $newPickup->format('Y-m-d H:i:s'), $newReturn->format('Y-m-d H:i:s'), 'Extended stay', $admin
        );

        $this->assertTrue($oldPickup->equalTo($history->old_pickup_at));
        $this->assertTrue($oldReturn->equalTo($history->old_return_at));
        $this->assertTrue($newPickup->equalTo($history->new_pickup_at));
        $this->assertTrue($newReturn->equalTo($history->new_return_at));
        $this->assertArrayHasKey('assignment_impact', $history->snapshot);
        $this->assertArrayHasKey('pricing_impact', $history->snapshot);
    }

    // ---- 42 pending offers cancelled on reschedule --------------------------------------------------------------------------------------------------------------------------------------

    public function test_reschedule_cancels_stale_pending_offers(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);
        $offer = $this->pendingOffer($booking, $driver, $vehicle);

        app(TaxiRescheduleService::class)->reschedule(
            $booking, now()->addDays(2)->format('Y-m-d H:i:s'), null, 'New time', $admin
        );

        $this->assertSame(TaxiDispatchOfferStatus::Cancelled->value, $offer->fresh()->status);
    }

    // ---- 43 assignment conflict handled ----------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_reschedule_releases_incompatible_assignment(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle, $admin);
        $driver->update(['is_active' => false]);

        $history = app(TaxiRescheduleService::class)->reschedule(
            $booking->fresh(), now()->addDays(2)->format('Y-m-d H:i:s'), null, 'Driver left the fleet', $admin
        );

        $this->assertSame('released', $history->snapshot['assignment_impact']);
        $fresh = $booking->fresh();
        $this->assertNull($fresh->assigned_driver_id);
        $this->assertNull($fresh->assigned_vehicle_id);
    }

    // ---- 44 pricing snapshot not overwritten ------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_reschedule_and_cancel_preserve_pricing_snapshot(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['total_amount' => 3200]);
        $snapshotBefore = $booking->pricing_snapshot;

        app(TaxiRescheduleService::class)->reschedule(
            $booking, now()->addDays(2)->format('Y-m-d H:i:s'), null, 'Later pickup', $admin
        );

        $afterReschedule = $booking->fresh();
        $this->assertSame('3200.00', (string) $afterReschedule->total_amount);
        $this->assertEquals($snapshotBefore, $afterReschedule->pricing_snapshot);

        $this->cancel($afterReschedule, $admin);

        $afterCancel = $booking->fresh();
        $this->assertSame('3200.00', (string) $afterCancel->total_amount);
        $this->assertEquals($snapshotBefore, $afterCancel->pricing_snapshot);
    }

    // ---- 45 customer reschedule authorization --------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_customer_reschedule_requires_setting_and_ownership(): void
    {
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $owner->id]);

        // Disabled by default: even the owner is blocked.
        $response = $this->actingAs($owner)->post(route('account.taxi.changes.reschedule', $booking, absolute: false), [
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'reason' => 'Later please',
            'confirmed' => true,
        ]);
        $response->assertForbidden();

        Setting::setValue('taxi.customer_reschedule.enabled', '1');

        $intruderResponse = $this->actingAs($intruder)->post(route('account.taxi.changes.reschedule', $booking, absolute: false), [
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'reason' => 'Hijack',
            'confirmed' => true,
        ]);
        $intruderResponse->assertNotFound();

        $ownerResponse = $this->actingAs($owner)->post(route('account.taxi.changes.reschedule', $booking, absolute: false), [
            'pickup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'reason' => 'Later please',
            'confirmed' => true,
        ]);
        $ownerResponse->assertRedirect();
        $this->assertDatabaseHas('taxi_booking_reschedules', ['taxi_booking_id' => $booking->id]);
    }

    // ---- 46 no-show policy --------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_no_show_uses_no_show_fee_and_status(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, [
            'status' => TaxiBookingStatus::DriverAssigned->value,
            'pickup_at' => now()->subHours(2),
        ]);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 100, 'no_show_fee_type' => 'fixed', 'no_show_fee_value' => 400]);

        $cancellation = app(TaxiCancellationService::class)->cancel($booking, 'no_show', 'Passenger never arrived', $admin, 'admin');

        $this->assertSame('no_show', $cancellation->status);
        $this->assertSame('400.00', (string) $cancellation->cancellation_fee);
        $this->assertSame(TaxiBookingStatus::NoShow->value, $booking->fresh()->status);
    }

    // ---- 47 no duplicate driver earning ------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_cancelled_trip_creates_no_driver_earning(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle, $admin);
        $this->cancel($booking->fresh(), $admin);

        $this->assertSame(0, TaxiDriverEarning::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 48 paid earning not silently deleted ---------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_completed_booking_keeps_earning_and_refuses_cancel(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile);

        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle, $admin);
        $assignment = $booking->fresh()->currentAssignment();

        $earning = TaxiDriverEarning::create([
            'earning_number' => 'TE-TEST-0001',
            'driver_id' => $driver->id,
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'taxi_booking_id' => $booking->id,
            'taxi_assignment_id' => $assignment->id,
            'currency' => 'INR',
            'calculation_type' => 'fixed',
            'gross_earning' => '300.00',
            'net_earning' => '300.00',
            'status' => 'paid',
            'earned_at' => now(),
            'paid_at' => now(),
        ]);

        $booking->forceFill(['status' => TaxiBookingStatus::Completed->value])->save();

        try {
            $this->cancel($booking->fresh(), $admin);
            $this->fail('Completed bookings must refuse cancellation.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('taxi_driver_earnings', ['id' => $earning->id, 'status' => 'paid']);
    }

    // ---- 49 audit events -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_cancellation_refund_and_reschedule_emit_audit_events(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 200]);

        $this->cancel($booking, $admin);
        $refund = app(TaxiRefundService::class)->create($booking->fresh(), '500.00', (string) Str::uuid(), 'Audit trail', $admin);
        app(TaxiRefundService::class)->process($refund, 'cash', 'CASH-1', $admin);

        $events = DB::table('activity_logs')->pluck('event')->all();
        $this->assertContains('taxi_booking_cancellation.created', $events);
        $this->assertContains('taxi_refund.created', $events);

        $booking2 = $this->bookingFor($vendor->vendorProfile);
        app(TaxiRescheduleService::class)->reschedule(
            $booking2, now()->addDays(2)->format('Y-m-d H:i:s'), null, 'Audit reschedule', $admin
        );
        $this->assertContains(
            'taxi_booking_reschedule.created',
            DB::table('activity_logs')->pluck('event')->all()
        );
    }

    // ---- 50 quote preview no audit spam ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_quote_preview_writes_no_audit_events(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 1000);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 100]);

        $before = DB::table('activity_logs')->count();
        app(TaxiCancellationService::class)->quote($booking);
        app(TaxiRescheduleService::class)->quote($booking, now()->addDays(2)->format('Y-m-d H:i:s'), null);

        $this->assertSame($before, DB::table('activity_logs')->count());
    }

    // ---- 51 module disabled -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_disabled_taxi_module_hides_change_pages(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);

        Setting::setValue('modules.taxi.enabled', '0');

        $this->actingAs($admin)->get(route('admin.taxi.changes.show', $booking, absolute: false))->assertNotFound();
        $this->actingAs($vendor)->get(route('vendor.taxi.changes.show', $booking, absolute: false))->assertNotFound();
    }

    // ---- 52 historical pricing snapshot unchanged ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_policy_edit_does_not_rewrite_historical_snapshot(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addMinutes(30)]);
        $this->pay($booking, 2500);
        $policy = $this->policy(['name' => 'Original', 'fee_type' => 'fixed', 'fee_value' => 500]);

        $cancellation = $this->cancel($booking, $admin);
        $snapshotBefore = $cancellation->calculation_snapshot;

        $policy->update(['fee_value' => 50]);

        $this->assertEquals($snapshotBefore, $cancellation->fresh()->calculation_snapshot);
        $this->assertSame('500.00', (string) $cancellation->fresh()->cancellation_fee);
    }

    // ---- 53 customer payload hides internal policy ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_customer_quote_hides_internal_policy(): void
    {
        Setting::setValue('taxi.customer_cancellation.enabled', '1');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id]);
        $this->pay($booking, 2500);
        $this->policy(['name' => 'Secret internal', 'fee_type' => 'fixed', 'fee_value' => 500]);

        $response = $this->actingAs($customer)->getJson(route('account.taxi.changes.quote', $booking, absolute: false));

        $response->assertOk();
        $response->assertJsonMissingPath('policy');
        $response->assertJsonStructure(['booking_total', 'amount_paid', 'cancellation_fee', 'refundable_amount', 'currency', 'cutoff_state', 'calculated_at']);
    }

    // ---- 54 double-refund concurrency protection ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_duplicate_request_key_returns_same_refund(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->pay($booking, 2500);
        $this->policy(['fee_type' => 'fixed', 'fee_value' => 500]);
        $this->cancel($booking, $admin);

        $key = (string) Str::uuid();
        $first = app(TaxiRefundService::class)->create($booking->fresh(), '500.00', $key, 'Original', $admin);
        $second = app(TaxiRefundService::class)->create($booking->fresh(), '500.00', $key, 'Retry after timeout', $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TaxiRefund::where('taxi_booking_id', $booking->id)->count());

        $summary = app(TaxiRefundService::class)->summary($booking->fresh());
        $this->assertSame(1500.0, $summary['refundable_remaining']);
    }

    // ---- 55 cancelled booking cannot restart auto-dispatch ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_cancelled_booking_cannot_restart_auto_dispatch(): void
    {
        Setting::setValue('taxi.dispatch.auto_enabled', '1');
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile);
        $this->cancel($booking);

        try {
            app(TaxiAutoDispatchService::class)->startForBooking($booking->fresh());
            $this->fail('Cancelled bookings must never restart auto-dispatch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('auto_dispatch', $e->errors());
        }

        $this->assertSame(0, TaxiDispatchOffer::where('taxi_booking_id', $booking->id)->count());
    }
}
