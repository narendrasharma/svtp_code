<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Enums\TaxiDriverEarningStatus;
use App\Enums\TaxiDriverPayoutStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverCompensationPlan;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverPayout;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use App\Services\TaxiBookingService;
use App\Services\TaxiDriverEarningService;
use App\Services\TaxiDriverPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaxiDriverEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('super-admin');

        return $user;
    }

    private function plan(array $overrides = []): TaxiDriverCompensationPlan
    {
        return TaxiDriverCompensationPlan::create(array_merge([
            'name' => 'Standard trip', 'currency' => 'INR', 'calculation_type' => 'fixed',
            'fixed_amount' => 500, 'is_active' => true,
        ], $overrides));
    }

    private function trip(?Driver $driver = null, array $overrides = []): TaxiBooking
    {
        $driver ??= Driver::factory()->create(['user_id' => User::factory()->create()->id]);
        $vehicle = Vehicle::factory()->create(['vendor_profile_id' => $driver->vendor_profile_id]);
        $booking = TaxiBooking::factory()->create(array_merge([
            'vendor_profile_id' => $driver->vendor_profile_id,
            'assigned_driver_id' => $driver->id, 'assigned_vehicle_id' => $vehicle->id,
            'status' => 'passenger_on_board', 'base_amount' => 2000, 'total_amount' => 2500,
            'quoted_distance_km' => 40, 'quoted_duration_minutes' => 150,
            'pricing_snapshot' => ['source' => 'retained', 'breakdown' => ['driver_allowance' => 100]],
        ], $overrides));
        $booking->assignments()->create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'assigned_at' => now()]);

        return $booking;
    }

    private function complete(TaxiBooking $booking): TaxiDriverEarning
    {
        app(TaxiBookingService::class)->changeStatus($booking, TaxiBookingStatus::Completed);

        return TaxiDriverEarning::where('taxi_booking_id', $booking->id)->firstOrFail();
    }

    private function earning(): TaxiDriverEarning
    {
        $this->plan();

        return $this->complete($this->trip());
    }

    private function payout(TaxiDriverEarning $earning): TaxiDriverPayout
    {
        return app(TaxiDriverPayoutService::class)->createPayout($earning->driver_id, [$earning->id]);
    }

    public function test_completed_trip_creates_earning(): void
    {
        $earning = $this->earning();

        $this->assertSame('500.00', $earning->gross_earning);
        $this->assertSame(TaxiDriverEarningStatus::Payable, $earning->status);
        $this->assertDatabaseCount('taxi_driver_earnings', 1);
    }

    public function test_non_completed_trip_has_no_earning(): void
    {
        $this->plan(['no_show_amount' => 100]);
        $booking = $this->trip();

        $this->assertNull(app(TaxiDriverEarningService::class)->recordForBooking($booking));
        $booking->update(['status' => 'no_show']);
        $this->assertNull(app(TaxiDriverEarningService::class)->recordForBooking($booking));
        $this->assertDatabaseCount('taxi_driver_earnings', 0);
    }

    public function test_repeated_completion_is_idempotent(): void
    {
        $earning = $this->earning();
        $service = app(TaxiDriverEarningService::class);

        $this->assertSame($earning->id, $service->recordForBooking($earning->booking)->id);
        $this->assertSame($earning->id, $service->recordForBooking($earning->booking)->id);
        $this->assertDatabaseCount('taxi_driver_earnings', 1);
    }

    public function test_reassignment_pays_only_final_driver(): void
    {
        $this->plan();
        $booking = $this->trip(null, ['status' => 'confirmed']);
        $previous = $booking->assigned_driver_id;
        $driver = Driver::factory()->create(['vendor_profile_id' => $booking->vendor_profile_id]);
        $vehicle = Vehicle::factory()->create(['vendor_profile_id' => $booking->vendor_profile_id]);
        app(TaxiBookingService::class)->assign($booking, $driver, $vehicle);
        $booking->refresh()->update(['status' => 'passenger_on_board']);

        $earning = $this->complete($booking->refresh());

        $this->assertSame($driver->id, $earning->driver_id);
        $this->assertDatabaseMissing('taxi_driver_earnings', ['driver_id' => $previous]);
    }

    public function test_fixed_calculation(): void
    {
        $this->plan(['fixed_amount' => 725]);
        $this->assertSame('725.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_percentage_total_calculation(): void
    {
        $this->plan(['calculation_type' => 'percent_total', 'percentage' => 20]);
        $this->assertSame('500.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_percentage_base_calculation(): void
    {
        $this->plan(['calculation_type' => 'percent_base', 'percentage' => 20]);
        $this->assertSame('400.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_per_km_calculation(): void
    {
        $this->plan(['calculation_type' => 'per_km', 'per_km_amount' => 12]);
        $this->assertSame('480.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_per_hour_calculation(): void
    {
        $this->plan(['calculation_type' => 'per_hour', 'per_hour_amount' => 200]);
        $this->assertSame('500.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_hybrid_calculation(): void
    {
        $this->plan(['calculation_type' => 'hybrid', 'fixed_amount' => 200, 'percentage' => 10]);
        $this->assertSame('450.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_minimum_earning(): void
    {
        $this->plan(['minimum_earning' => 800]);
        $earning = $this->complete($this->trip());
        $this->assertSame('800.00', $earning->gross_earning);
        $this->assertTrue($earning->calculation_snapshot['result']['minimum_applied']);
    }

    public function test_driver_specific_precedence(): void
    {
        $booking = $this->trip();
        $this->plan(['driver_id' => $booking->assigned_driver_id, 'vendor_profile_id' => $booking->vendor_profile_id, 'fixed_amount' => 900]);
        $this->plan(['vendor_profile_id' => $booking->vendor_profile_id, 'fixed_amount' => 700]);
        $this->plan();
        $this->assertSame('900.00', $this->complete($booking)->gross_earning);
    }

    public function test_vendor_default_precedence(): void
    {
        $booking = $this->trip();
        $this->plan(['vendor_profile_id' => $booking->vendor_profile_id, 'fixed_amount' => 700]);
        $this->plan();
        $this->assertSame('700.00', $this->complete($booking)->gross_earning);
    }

    public function test_platform_default_fallback(): void
    {
        $this->plan();
        $this->plan(['vendor_profile_id' => VendorProfile::factory()->create()->id, 'fixed_amount' => 900]);
        $this->assertSame('500.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_inactive_plan_is_ignored(): void
    {
        $this->plan();
        $this->plan(['is_active' => false, 'fixed_amount' => 900]);
        $this->assertSame('500.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_future_and_expired_plans_are_ignored(): void
    {
        $this->plan();
        $this->plan(['effective_from' => now()->addDay(), 'fixed_amount' => 900]);
        $this->plan(['effective_until' => now()->subDay(), 'fixed_amount' => 800]);
        $this->assertSame('500.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_snapshot_and_booking_pricing_stay_unchanged(): void
    {
        $plan = $this->plan();
        $booking = $this->trip();
        $pricing = $booking->pricing_snapshot;
        $earning = $this->complete($booking);
        $snapshot = $earning->calculation_snapshot;
        $plan->update(['fixed_amount' => 999]);
        app(TaxiDriverEarningService::class)->recordForBooking($booking->refresh());

        $this->assertSame($snapshot, $earning->fresh()->calculation_snapshot);
        $this->assertSame('500.00', $earning->fresh()->gross_earning);
        $this->assertSame($pricing, $booking->fresh()->pricing_snapshot);
        $this->assertSame('2500.00', $booking->fresh()->total_amount);
    }

    public function test_positive_adjustment(): void
    {
        $earning = $this->earning();
        $actor = $this->admin();
        $adjustment = app(TaxiDriverEarningService::class)->applyAdjustment($earning, '100', 'bonus', 'Extra assistance', $actor);

        $this->assertSame('600.00', $earning->fresh()->net_earning);
        $this->assertSame($actor->id, $adjustment->created_by);
    }

    public function test_negative_adjustment(): void
    {
        $earning = $this->earning();
        $snapshot = $earning->calculation_snapshot;
        app(TaxiDriverEarningService::class)->applyAdjustment($earning, '-100', 'penalty', 'Agreed correction', $this->admin());

        $this->assertSame('400.00', $earning->fresh()->net_earning);
        $this->assertSame('-100.00', $earning->fresh()->adjustments_total);
        $this->assertSame($snapshot, $earning->fresh()->calculation_snapshot);
    }

    public function test_allocated_earning_cannot_be_adjusted(): void
    {
        $earning = $this->earning();
        $this->payout($earning);
        $this->expectException(ValidationException::class);
        app(TaxiDriverEarningService::class)->applyAdjustment($earning, '-100', 'penalty', 'Correction');
    }

    public function test_hold_becomes_eligible_when_due(): void
    {
        $this->freezeTime();
        Setting::setValue('taxi.driver_earnings.hold_days', '2');
        $earning = $this->earning();
        $this->assertSame(TaxiDriverEarningStatus::Pending, $earning->status);
        $this->assertSame(0, TaxiDriverEarning::payable()->count());
        $this->travel(3)->days();
        $this->assertSame(1, TaxiDriverEarning::payable()->count());
        $this->assertSame('500.00', $this->payout($earning)->amount);
    }

    public function test_pending_earning_requires_release(): void
    {
        Setting::setValue('taxi.driver_earnings.auto_payable_on_complete', '0');
        $earning = $this->earning();
        $this->assertSame(0, TaxiDriverEarning::payable()->count());
        app(TaxiDriverEarningService::class)->markPayable($earning, $this->admin());
        $this->assertSame('500.00', $this->payout($earning->fresh())->amount);
    }

    public function test_admin_payout_ignores_client_total(): void
    {
        $earning = $this->earning();
        $this->actingAs($this->admin())->post(route('admin.taxi.payouts.store', absolute: false), [
            'driver_id' => $earning->driver_id, 'earning_ids' => [$earning->id], 'amount' => 1, 'total' => 99999,
        ])->assertRedirect();
        $this->assertDatabaseHas('taxi_driver_payouts', ['amount' => 500, 'driver_id' => $earning->driver_id]);
        $this->assertDatabaseHas('taxi_driver_payout_items', ['earning_id' => $earning->id, 'amount' => 500]);
    }

    public function test_vendor_creates_own_payout(): void
    {
        $earning = $this->earning();
        $vendor = $earning->vendorProfile->user;
        $vendor->update(['role' => 'vendor']);
        $this->actingAs($vendor)->post(route('vendor.taxi.payouts.store', absolute: false), [
            'driver_id' => $earning->driver_id, 'earning_ids' => [$earning->id],
        ])->assertRedirect();
        $this->assertDatabaseHas('taxi_driver_payouts', ['vendor_profile_id' => $earning->vendor_profile_id, 'amount' => 500]);
    }

    public function test_foreign_driver_payout_is_rejected(): void
    {
        $earning = $this->earning();
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id]);
        $this->actingAs($vendor)->post(route('vendor.taxi.payouts.store', absolute: false), [
            'driver_id' => $earning->driver_id, 'earning_ids' => [$earning->id],
        ])->assertNotFound();
        $this->assertDatabaseCount('taxi_driver_payouts', 0);
    }

    public function test_mixed_currency_payout_is_rejected(): void
    {
        $earning = $this->earning();
        $this->plan(['currency' => 'USD']);
        $other = $this->complete($this->trip($earning->driver, ['currency' => 'USD']));
        $this->expectException(ValidationException::class);
        app(TaxiDriverPayoutService::class)->createPayout($earning->driver_id, [$earning->id, $other->id]);
    }

    public function test_duplicate_allocation_is_rejected(): void
    {
        $earning = $this->earning();
        $this->payout($earning);
        $this->expectException(ValidationException::class);
        $this->payout($earning);
    }

    public function test_mark_paid_settles_only_allocations_and_keeps_reference(): void
    {
        $earning = $this->earning();
        $other = $this->complete($this->trip($earning->driver));
        $payout = app(TaxiDriverPayoutService::class)->markPaid($this->payout($earning), $this->admin(), 'BANK-123');

        $this->assertSame(TaxiDriverPayoutStatus::Paid, $payout->status);
        $this->assertSame('BANK-123', $payout->payment_reference);
        $this->assertNotNull($payout->paid_at);
        $this->assertSame(TaxiDriverEarningStatus::Paid, $earning->fresh()->status);
        $this->assertSame('500.00', $earning->fresh()->paid_amount);
        $this->assertSame('0.00', $other->fresh()->paid_amount);
    }

    public function test_cancel_releases_allocations(): void
    {
        $earning = $this->earning();
        $payout = app(TaxiDriverPayoutService::class)->cancelPayout($this->payout($earning), $this->admin(), 'Wrong batch');
        $this->assertSame(TaxiDriverPayoutStatus::Cancelled, $payout->status);
        $this->assertSame(0, $payout->items()->count());
        $this->assertSame('500.00', $this->payout($earning)->amount);
    }

    public function test_paid_payout_cannot_cancel(): void
    {
        $payout = app(TaxiDriverPayoutService::class)->markPaid($this->payout($this->earning()), $this->admin());
        $this->expectException(ValidationException::class);
        app(TaxiDriverPayoutService::class)->cancelPayout($payout);
    }

    public function test_payout_notifies_driver_once(): void
    {
        $earning = $this->earning();
        Notification::fake();
        $service = app(TaxiDriverPayoutService::class);
        $payout = $service->markPaid($this->payout($earning), $actor = $this->admin(), 'BANK-123');
        $service->markPaid($payout, $actor, 'BANK-123');
        Notification::assertSentTo($earning->driver->user, CrmNotification::class, fn ($notice) => $notice->kind === 'taxi_driver_payout_paid' && $notice->data['payment_reference'] === 'BANK-123');
        Notification::assertSentToTimes($earning->driver->user, CrmNotification::class, 1);
    }

    public function test_duplicate_event_notifies_once(): void
    {
        $this->plan();
        $booking = $this->trip(null, ['status' => 'completed']);
        Notification::fake();
        $service = app(TaxiDriverEarningService::class);
        $service->recordForBooking($booking);
        $service->recordForBooking($booking);
        Notification::assertSentToTimes($booking->assignedDriver->user, CrmNotification::class, 1);
    }

    public function test_financial_events_are_audited(): void
    {
        $this->actingAs($actor = $this->admin());
        $earning = $this->earning();
        app(TaxiDriverEarningService::class)->applyAdjustment($earning, 100, 'bonus', 'Assistance', $actor);
        app(TaxiDriverPayoutService::class)->markPaid($this->payout($earning->fresh()), $actor);
        foreach (['taxi_driver_compensation_plan.created', 'taxi_driver_earning.created', 'taxi_driver_earning_adjustment.created', 'taxi_driver_payout.created', 'taxi_driver_payout.status_changed'] as $event) {
            $this->assertDatabaseHas('activity_logs', ['event' => $event, 'actor_user_id' => $actor->id]);
        }
    }

    public function test_vendor_lists_only_own_earnings(): void
    {
        $earning = $this->earning();
        $foreign = $this->complete($this->trip());
        $vendor = $earning->vendorProfile->user;
        $vendor->update(['role' => 'vendor']);
        $this->actingAs($vendor)->get(route('vendor.taxi.earnings.index', absolute: false))
            ->assertInertia(fn (Assert $page) => $page->component('Vendor/Taxi/Earnings/Index')->has('earnings.data', 1)->where('earnings.data.0.id', $earning->id));
        $this->get(route('vendor.taxi.earnings.show', $foreign->id, false))->assertNotFound();
    }

    public function test_driver_lists_only_own_earnings_and_payouts(): void
    {
        $earning = $this->earning();
        $foreign = $this->complete($this->trip());
        $this->payout($earning);
        $this->payout($foreign);
        $this->actingAs($earning->driver->user)->get(route('driver.taxi.earnings.index', absolute: false))
            ->assertInertia(fn (Assert $page) => $page->component('Driver/Taxi/Earnings/Index')->has('earnings.data', 1)->where('earnings.data.0.id', $earning->id)->has('payouts.data', 1));
        $this->get(route('driver.taxi.earnings.show', $foreign->id, false))->assertNotFound();
    }

    public function test_driver_cannot_adjust(): void
    {
        $earning = $this->earning();
        $this->actingAs($earning->driver->user)->post(route('admin.taxi.earnings.adjustments.store', $earning->id, false), ['amount' => 999, 'kind' => 'bonus', 'reason' => 'Forged'])
            ->assertForbidden();
        $this->assertDatabaseCount('taxi_driver_earning_adjustments', 0);
    }

    public function test_module_disabled_blocks_financial_pages(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');
        $this->actingAs($this->admin())->get(route('admin.taxi.earnings.index', absolute: false))->assertNotFound();
    }

    public function test_plan_forms_and_payout_pages_render(): void
    {
        $earning = $this->earning();
        $payout = $this->payout($earning);
        $this->actingAs($this->admin());
        $this->get(route('admin.taxi.plans.create', absolute: false))->assertInertia(fn (Assert $p) => $p->component('Admin/Taxi/CompensationPlans/Form'));
        $this->get(route('admin.taxi.payouts.create', absolute: false))->assertInertia(fn (Assert $p) => $p->component('Admin/Taxi/Payouts/Create'));
        $this->get(route('admin.taxi.payouts.show', $payout->id, false))->assertInertia(fn (Assert $p) => $p->component('Admin/Taxi/Payouts/Show'));
        $this->get(route('admin.taxi.earnings.show', $earning->id, false))->assertInertia(fn (Assert $p) => $p->component('Admin/Taxi/Earnings/Show'));
    }

    public function test_vendor_plan_writes_cannot_escape_ownership(): void
    {
        $earning = $this->earning();
        $vendor = $earning->vendorProfile->user;
        $vendor->update(['role' => 'vendor']);
        $foreign = Driver::factory()->create();
        $this->actingAs($vendor)->post(route('vendor.taxi.plans.store', absolute: false), [
            'name' => 'Own plan', 'currency' => 'INR', 'calculation_type' => 'fixed',
            'fixed_amount' => 650, 'vendor_profile_id' => $foreign->vendor_profile_id,
        ])->assertRedirect();
        $this->assertDatabaseHas('taxi_driver_compensation_plans', ['name' => 'Own plan', 'vendor_profile_id' => $earning->vendor_profile_id]);
        $this->post(route('vendor.taxi.plans.store', absolute: false), [
            'name' => 'Foreign driver', 'currency' => 'INR', 'calculation_type' => 'fixed',
            'driver_id' => $foreign->id,
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('taxi_driver_compensation_plans', ['name' => 'Foreign driver']);
    }

    public function test_vendor_financial_pages_exclude_foreign_records(): void
    {
        $earning = $this->earning();
        $vendor = $earning->vendorProfile->user;
        $vendor->update(['role' => 'vendor']);
        $ownPlan = $this->plan(['vendor_profile_id' => $earning->vendor_profile_id]);
        $foreignPlan = $this->plan(['vendor_profile_id' => VendorProfile::factory()->create()->id]);
        $ownPayout = $this->payout($earning);
        $foreignPayout = $this->payout($this->complete($this->trip()));
        $this->actingAs($vendor)->get(route('vendor.taxi.plans.index', absolute: false))
            ->assertInertia(fn (Assert $p) => $p->component('Vendor/Taxi/CompensationPlans/Index')->has('plans.data', 1)->where('plans.data.0.id', $ownPlan->id));
        $this->get(route('vendor.taxi.plans.edit', $foreignPlan->id, false))->assertNotFound();
        $this->get(route('vendor.taxi.plans.edit', $ownPlan->id, false))->assertInertia(fn (Assert $p) => $p->component('Vendor/Taxi/CompensationPlans/Form'));
        $this->get(route('vendor.taxi.payouts.index', absolute: false))->assertInertia(fn (Assert $p) => $p->component('Vendor/Taxi/Payouts/Index')->has('payouts.data', 1));
        $this->get(route('vendor.taxi.payouts.create', ['driver_id' => $earning->driver_id], false))->assertInertia(fn (Assert $p) => $p->component('Vendor/Taxi/Payouts/Create'));
        $this->get(route('vendor.taxi.payouts.show', $ownPayout->id, false))->assertInertia(fn (Assert $p) => $p->component('Vendor/Taxi/Payouts/Show'));
        $this->get(route('vendor.taxi.payouts.show', $foreignPayout->id, false))->assertNotFound();
        $this->patch(route('vendor.taxi.payouts.mark-paid', $foreignPayout->id, false))->assertNotFound();
        $this->get(route('vendor.taxi.earnings.show', $earning->id, false))->assertInertia(fn (Assert $p) => $p->component('Vendor/Taxi/Earnings/Show'));
    }

    public function test_driver_detail_is_read_only_and_shows_payment(): void
    {
        $earning = $this->earning();
        app(TaxiDriverPayoutService::class)->markPaid($this->payout($earning), $this->admin(), 'BANK-789');
        $this->actingAs($earning->driver->user)->get(route('driver.taxi.earnings.show', $earning->id, false))
            ->assertInertia(fn (Assert $p) => $p->component('Driver/Taxi/Earnings/Show')->where('earning.payout_items.0.payout.payment_reference', 'BANK-789'));
        $this->post(route('admin.taxi.payouts.store', absolute: false), ['driver_id' => $earning->driver_id, 'earning_ids' => [$earning->id]])->assertForbidden();
        $this->post(route('admin.taxi.plans.store', absolute: false), [])->assertForbidden();
    }

    public function test_payout_rejects_earnings_from_former_vendor_ownership(): void
    {
        $driver = Driver::factory()->create();
        $this->plan();
        $earning = $this->complete($this->trip($driver));
        $profile = VendorProfile::factory()->create();
        $driver->update(['vendor_profile_id' => $profile->id]);
        $profile->user->update(['role' => 'vendor']);
        $this->actingAs($profile->user)->post(route('vendor.taxi.payouts.store', absolute: false), [
            'driver_id' => $driver->id, 'earning_ids' => [$earning->id],
        ])->assertNotFound();
        $this->assertDatabaseCount('taxi_driver_payouts', 0);
    }

    public function test_currency_summaries_are_separate(): void
    {
        $earning = $this->earning();
        $this->plan(['currency' => 'USD', 'fixed_amount' => 25]);
        $this->complete($this->trip($earning->driver, ['currency' => 'USD']));
        $service = app(TaxiDriverEarningService::class);
        $this->assertSame('500.00', $service->summaryForDriver($earning->driver, 'INR')['payable_balance']);
        $this->assertSame('25.00', $service->summaryForDriver($earning->driver, 'USD')['payable_balance']);
    }

    public function test_future_hold_cannot_be_paid(): void
    {
        Setting::setValue('taxi.driver_earnings.hold_days', '2');
        $earning = $this->earning();
        $this->expectException(ValidationException::class);
        $this->payout($earning);
    }

    public function test_void_earning_is_not_payable_and_snapshot_is_preserved(): void
    {
        $earning = $this->earning();
        $snapshot = $earning->calculation_snapshot;
        app(TaxiDriverEarningService::class)->voidEarning($earning, $this->admin(), 'Duplicate source');
        $this->assertSame($snapshot, $earning->fresh()->calculation_snapshot);
        $this->expectException(ValidationException::class);
        $this->payout($earning->fresh());
    }

    public function test_unmatched_vehicle_plan_is_ignored(): void
    {
        $this->plan();
        $this->plan(['vehicle_type_id' => VehicleType::factory()->create()->id, 'fixed_amount' => 999]);
        $this->assertSame('500.00', $this->complete($this->trip())->gross_earning);
    }

    public function test_admin_plan_create_update_toggle_keeps_history(): void
    {
        $this->actingAs($this->admin())->post(route('admin.taxi.plans.store', absolute: false), [
            'name' => 'Admin default', 'currency' => 'INR', 'calculation_type' => 'fixed', 'fixed_amount' => 700,
        ])->assertRedirect();
        $plan = TaxiDriverCompensationPlan::where('name', 'Admin default')->firstOrFail();
        $earning = $this->complete($this->trip());
        $this->put(route('admin.taxi.plans.update', $plan->id, false), [
            'name' => 'Admin default', 'currency' => 'INR', 'calculation_type' => 'fixed', 'fixed_amount' => 900,
        ])->assertRedirect();
        $this->patch(route('admin.taxi.plans.toggle', $plan->id, false))->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame('700.00', $earning->fresh()->gross_earning);
    }

    public function test_staff_without_finance_permissions_is_blocked(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole(Role::create(['name' => 'earnings-denied', 'guard_name' => 'web']));
        $this->actingAs($staff)->get(route('admin.taxi.earnings.index', absolute: false))->assertForbidden();
        $this->post(route('admin.taxi.payouts.store', absolute: false), [])->assertForbidden();
    }

    public function test_completion_ignores_browser_earning_amounts(): void
    {
        $this->plan();
        $booking = $this->trip();
        $this->actingAs($this->admin())->patch(route('admin.taxi.bookings.status', $booking->id, false), [
            'status' => 'completed', 'gross_earning' => 99999, 'net_earning' => 99999,
            'total_amount' => 1, 'pricing_snapshot' => ['forged' => true],
        ])->assertRedirect();
        $this->assertDatabaseHas('taxi_driver_earnings', ['taxi_booking_id' => $booking->id, 'gross_earning' => 500]);
        $this->assertSame('2500.00', $booking->fresh()->total_amount);
        $this->assertSame('retained', $booking->fresh()->pricing_snapshot['source']);
    }

    public function test_historical_earning_fields_cannot_be_edited(): void
    {
        $earning = $this->earning();
        $this->expectException(\LogicException::class);
        $earning->update(['gross_earning' => 999]);
    }
}
