<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingService;
use App\Services\MarketplaceCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 6: vendor bookings + commission/earnings foundation.
 *
 * Covers vendor assignment snapshots, commission math, vendor access
 * scoping, detail privacy, earnings summary semantics and admin surfaces.
 * Broader regressions (guest/customer booking, confirmation, My Bookings,
 * cancellation, invoice, moderation) are covered by the existing suite.
 */
class VendorBookingCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function service(): BookingService
    {
        return app(BookingService::class);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    protected function vendor(): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return $user;
    }

    protected function vendorTour(VendorProfile $profile, array $overrides = []): TourPackage
    {
        return TourPackage::factory()->forVendor($profile)->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function adminTour(array $overrides = []): TourPackage
    {
        return TourPackage::factory()->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function book(TourPackage $tour, ?User $user = null): Booking
    {
        return $this->service()->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Test Traveller', 'email' => 'traveller@example.com', 'phone' => '9876543210'],
            $user,
            BookingSource::Website
        );
    }

    // ---- Vendor assignment -------------------------------------------------

    public function test_vendor_owned_tour_booking_snapshots_vendor(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $this->assertSame($vendor->vendorProfile->id, (int) $booking->vendor_profile_id);
        $this->assertTrue($booking->isVendorBooking());
    }

    public function test_admin_tour_booking_snapshot_is_null_with_zeroed_split(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $booking = $this->book($this->adminTour());

        $this->assertNull($booking->vendor_profile_id);
        $this->assertFalse($booking->isVendorBooking());
        $this->assertSame('10000.00', $booking->gross_amount);
        $this->assertNull($booking->platform_commission_percentage);
        $this->assertSame('0.00', $booking->platform_commission_amount);
        $this->assertSame('0.00', $booking->vendor_earning_amount);
    }

    public function test_ownership_change_does_not_rewrite_old_booking(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $tour = $this->vendorTour($vendorA->vendorProfile);

        $oldBooking = $this->book($tour);
        $tour->update(['vendor_profile_id' => $vendorB->vendorProfile->id]);

        $this->assertSame($vendorA->vendorProfile->id, (int) $oldBooking->refresh()->vendor_profile_id);
    }

    public function test_new_booking_after_ownership_change_uses_new_vendor(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $tour = $this->vendorTour($vendorA->vendorProfile);
        $this->book($tour);

        $tour->update(['vendor_profile_id' => $vendorB->vendorProfile->id]);
        $newBooking = $this->book($tour);

        $this->assertSame($vendorB->vendorProfile->id, (int) $newBooking->vendor_profile_id);
    }

    public function test_snapshot_fields_are_not_mass_assignable(): void
    {
        $booking = new Booking([
            'vendor_profile_id' => 999,
            'gross_amount' => '1.00',
            'platform_commission_percentage' => '99.00',
            'platform_commission_amount' => '1.00',
            'vendor_earning_amount' => '2.00',
        ]);

        $this->assertNull($booking->vendor_profile_id);
        $this->assertNull($booking->gross_amount);
        $this->assertNull($booking->platform_commission_percentage);
        $this->assertNull($booking->platform_commission_amount);
        $this->assertNull($booking->vendor_earning_amount);
    }

    public function test_client_cannot_assign_vendor_or_commission_over_http(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $other = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);

        $this->post(route('booking.store'), [
            'package_id' => $tour->id,
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 2,
            'total_children' => 0,
            'customer_name' => 'Sneaky Guest',
            'customer_phone' => '9000000001',
            'vendor_profile_id' => $other->vendorProfile->id,
            'platform_commission_percentage' => '0.01',
            'platform_commission_amount' => '0.01',
            'vendor_earning_amount' => '999999.00',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $this->assertSame($vendor->vendorProfile->id, (int) $booking->vendor_profile_id);
        $this->assertSame('10.00', $booking->platform_commission_percentage);
        $this->assertSame('1000.00', $booking->platform_commission_amount);
        $this->assertSame('9000.00', $booking->vendor_earning_amount);
    }

    // ---- Commission math ---------------------------------------------------

    public function test_default_platform_commission_is_applied(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $booking = $this->book($this->vendorTour($this->vendor()->vendorProfile));

        $this->assertSame('10000.00', $booking->gross_amount);
        $this->assertSame('10.00', $booking->platform_commission_percentage);
        $this->assertSame('1000.00', $booking->platform_commission_amount);
        $this->assertSame('9000.00', $booking->vendor_earning_amount);
    }

    public function test_gross_equals_commission_plus_earning_invariant(): void
    {
        foreach ([
            ['10000.00', '10.00'],
            ['999.99', '10.00'],
            ['10.05', '10.00'],
            ['3333.33', '12.50'],
            ['1.00', '33.33'],
            ['250000.00', '7.25'],
        ] as [$gross, $percentage]) {
            $split = MarketplaceCommissionService::splitAmounts($gross, $percentage);
            $recombined = number_format((float) $split['commission'] + (float) $split['earning'], 2, '.', '');
            $this->assertSame($gross, $recombined, "invariant failed for gross {$gross} @ {$percentage}%");
        }
    }

    public function test_decimal_calculation_rounds_half_up(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $booking = $this->service()->createTourBooking(
            $this->vendorTour($vendor->vendorProfile, ['price' => 999.99]),
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Decimal Guest', 'phone' => '9000000002'],
            null,
            BookingSource::Website
        );

        $this->assertSame('999.99', $booking->gross_amount);
        $this->assertSame('100.00', $booking->platform_commission_amount);
        $this->assertSame('899.99', $booking->vendor_earning_amount);
    }

    public function test_zero_percent_commission_gives_full_earning(): void
    {
        Setting::setValue('platform_commission_percentage', '0');

        $booking = $this->book($this->vendorTour($this->vendor()->vendorProfile));

        $this->assertSame('0.00', $booking->platform_commission_percentage);
        $this->assertSame('0.00', $booking->platform_commission_amount);
        $this->assertSame('10000.00', $booking->vendor_earning_amount);
    }

    public function test_hundred_percent_commission_gives_zero_earning(): void
    {
        Setting::setValue('platform_commission_percentage', '100');

        $booking = $this->book($this->vendorTour($this->vendor()->vendorProfile));

        $this->assertSame('100.00', $booking->platform_commission_percentage);
        $this->assertSame('10000.00', $booking->platform_commission_amount);
        $this->assertSame('0.00', $booking->vendor_earning_amount);
    }

    public function test_setting_change_affects_future_bookings_only(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);
        $oldBooking = $this->book($tour);

        Setting::setValue('platform_commission_percentage', '15');
        $newBooking = $this->book($tour);

        $this->assertSame('10.00', $oldBooking->refresh()->platform_commission_percentage);
        $this->assertSame('1000.00', $oldBooking->platform_commission_amount);
        $this->assertSame('15.00', $newBooking->platform_commission_percentage);
        $this->assertSame('1500.00', $newBooking->platform_commission_amount);
        $this->assertSame('8500.00', $newBooking->vendor_earning_amount);
    }

    public function test_invalid_setting_falls_back_safely(): void
    {
        $service = app(MarketplaceCommissionService::class);

        $this->assertSame(MarketplaceCommissionService::FALLBACK_PERCENTAGE, MarketplaceCommissionService::normalizePercentage('not-a-number'));
        $this->assertSame(MarketplaceCommissionService::FALLBACK_PERCENTAGE, MarketplaceCommissionService::normalizePercentage('-5'));
        $this->assertSame(MarketplaceCommissionService::FALLBACK_PERCENTAGE, MarketplaceCommissionService::normalizePercentage('101'));
        $this->assertSame('12.50', MarketplaceCommissionService::normalizePercentage('12.5'));
        $this->assertSame('10.00', $service->defaultPercentage());
    }

    public function test_factory_rows_carry_coherent_snapshots(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile, ['price' => 5000]);
        $booking = Booking::factory()->create(['package_id' => $tour->id, 'total_adults' => 2, 'total_children' => 0]);

        $this->assertSame($vendor->vendorProfile->id, (int) $booking->vendor_profile_id);
        $this->assertSame('10000.00', $booking->gross_amount);
        $this->assertSame('1000.00', $booking->platform_commission_amount);
        $this->assertSame('9000.00', $booking->vendor_earning_amount);
    }

    // ---- Vendor access -----------------------------------------------------

    public function test_vendor_sees_only_assigned_bookings(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $this->book($this->vendorTour($vendorA->vendorProfile));
        $this->book($this->vendorTour($vendorB->vendorProfile));
        $this->book($this->adminTour());

        $this->actingAs($vendorA)->get(route('vendor.bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('bookings.data', 1)
                ->where('summary.assigned_bookings', 1));
    }

    public function test_vendor_cannot_view_other_vendor_booking(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $bookingB = $this->book($this->vendorTour($vendorB->vendorProfile));

        $this->actingAs($vendorA)->get(route('vendor.bookings.show', $bookingB))->assertForbidden();
    }

    public function test_vendor_cannot_view_admin_owned_booking(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->adminTour());

        $this->actingAs($vendor)->get(route('vendor.bookings.show', $booking))->assertForbidden();
    }

    public function test_customer_cannot_access_vendor_bookings(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($this->customer())->get(route('vendor.bookings.index'))->assertForbidden();
        $this->actingAs($this->customer())->get(route('vendor.bookings.show', $booking))->assertForbidden();
    }

    public function test_guest_cannot_access_vendor_bookings(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $this->get(route('vendor.bookings.index'))->assertRedirect(route('login'));
        $this->get(route('vendor.bookings.show', $booking))->assertRedirect(route('login'));
    }

    public function test_admin_still_sees_all_bookings(): void
    {
        $vendor = $this->vendor();
        $this->book($this->vendorTour($vendor->vendorProfile));
        $this->book($this->adminTour());

        $this->actingAs($this->admin())->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('bookings.data', 2));
    }

    public function test_impersonated_vendor_obeys_scope(): void
    {
        $admin = $this->admin();
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $bookingA = $this->book($this->vendorTour($vendorA->vendorProfile));
        $bookingB = $this->book($this->vendorTour($vendorB->vendorProfile));

        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendorA))->assertRedirect();

        $this->get(route('vendor.bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $bookingA->id)
                ->where('summary.assigned_bookings', 1));

        $this->get(route('vendor.bookings.show', $bookingA))->assertOk();
        $this->get(route('vendor.bookings.show', $bookingB))->assertForbidden();
        // No hidden admin powers while impersonating.
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    // ---- Detail privacy + read-only ----------------------------------------

    public function test_vendor_detail_shows_operational_data_only(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $response = $this->actingAs($vendor)->get(route('vendor.bookings.show', $booking));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']['booking']);

        $this->assertStringContainsString('traveller@example.com', $json);
        $this->assertStringContainsString('9876543210', $json);
        $this->assertStringContainsString('vendor_earning_amount', $json);
        $this->assertStringNotContainsString('qr_code_string', $json);
        $this->assertStringNotContainsString('payment_reference', $json);
        $this->assertStringNotContainsString('password', strtolower($json));
        $this->assertStringNotContainsString('review_note', $json);
        $this->assertStringNotContainsString('remember_token', $json);
    }

    public function test_vendor_cannot_mutate_payment_or_status(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)
            ->patch(route('admin.bookings.status', $booking), ['payment_status' => 'paid'])
            ->assertForbidden();

        $this->actingAs($vendor)
            ->patch('/vendor/bookings/'.$booking->id.'/status', ['payment_status' => 'paid'])
            ->assertNotFound();

        $this->assertSame(PaymentStatus::Unpaid, $booking->refresh()->payment_status);
    }

    public function test_vendor_cannot_see_admin_cancellation_review_notes(): void
    {
        $vendor = $this->vendor();
        $customer = $this->customer();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile), $customer);

        $cancellation = $booking->cancellationRequests()->create([
            'user_id' => $customer->id,
            'reason' => 'Change of plans',
            'status' => 'pending',
        ]);
        $cancellation->update(['review_note' => 'Secret admin note', 'reviewed_by' => $this->admin()->id]);

        $response = $this->actingAs($vendor)->get(route('vendor.bookings.show', $booking));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']['booking']);
        $this->assertStringContainsString('Change of plans', $json);
        $this->assertStringNotContainsString('Secret admin note', $json);
    }

    // ---- Earnings summary --------------------------------------------------

    public function test_earnings_summary_counts_only_paid_non_cancelled(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);

        $paid = $this->book($tour);
        $this->service()->markPayment($paid, PaymentStatus::Paid);

        $unpaid = $this->book($tour);

        $cancelled = $this->book($tour);
        $this->service()->markPayment($cancelled, PaymentStatus::Paid);
        $this->service()->changeStatus($cancelled, BookingStatus::Cancelled);

        $this->actingAs($vendor)->get(route('vendor.bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.assigned_bookings', 3)
                ->where('summary.paid_gross', '10000.00')
                ->where('summary.platform_commission', '1000.00')
                ->where('summary.recorded_vendor_earnings', '9000.00'));

        $this->assertSame($paid->id, (int) $paid->id);
        $this->assertSame($unpaid->id, (int) $unpaid->id);
    }

    // ---- Admin surfaces ----------------------------------------------------

    public function test_admin_booking_list_shows_vendor_and_supports_filter(): void
    {
        $vendor = $this->vendor();
        $vendorBooking = $this->book($this->vendorTour($vendor->vendorProfile));
        $adminBooking = $this->book($this->adminTour());

        $this->actingAs($this->admin())->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bookings.data.0.vendor_profile.business_name', $vendor->vendorProfile->business_name)
                ->where('bookings.data.1.vendor_profile', null));

        $this->actingAs($this->admin())
            ->get(route('admin.bookings.index', ['vendor' => $vendor->vendorProfile->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $vendorBooking->id));

        $this->actingAs($this->admin())
            ->get(route('admin.bookings.index', ['vendor' => 'admin']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $adminBooking->id));
    }

    public function test_admin_booking_detail_shows_split(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($this->admin())->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('booking.vendor_profile.business_name', $vendor->vendorProfile->business_name)
                ->where('booking.platform_commission_percentage', '10.00')
                ->where('booking.platform_commission_amount', '1000.00')
                ->where('booking.vendor_earning_amount', '9000.00'));
    }

    public function test_marketplace_setting_validation(): void
    {
        $admin = $this->admin();

        foreach (['-5', '101', 'not-a-number'] as $invalid) {
            $this->actingAs($admin)
                ->post(route('admin.settings.marketplace.update'), ['platform_commission_percentage' => $invalid, 'minimum_withdrawal_amount' => '1000'])
                ->assertSessionHasErrors('platform_commission_percentage');
        }

        foreach (['-10', 'not-a-number'] as $invalid) {
            $this->actingAs($admin)
                ->post(route('admin.settings.marketplace.update'), ['platform_commission_percentage' => '10', 'minimum_withdrawal_amount' => $invalid])
                ->assertSessionHasErrors('minimum_withdrawal_amount');
        }

        $this->actingAs($admin)
            ->post(route('admin.settings.marketplace.update'), ['platform_commission_percentage' => '12.5', 'minimum_withdrawal_amount' => '1500'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('12.50', Setting::getValue('platform_commission_percentage'));
        $this->assertSame('1500.00', Setting::getValue('minimum_withdrawal_amount'));
    }

    public function test_historical_bookings_unchanged_after_setting_update_via_admin(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);
        $booking = $this->book($tour);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.marketplace.update'), ['platform_commission_percentage' => '20', 'minimum_withdrawal_amount' => '1000']);

        $this->assertSame('10.00', $booking->refresh()->platform_commission_percentage);
        $this->assertSame('1000.00', $booking->platform_commission_amount);
    }
}
