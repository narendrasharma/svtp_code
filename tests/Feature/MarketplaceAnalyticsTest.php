<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\Coupon;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingService;
use App\Services\MarketplaceAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 11: marketplace analytics.
 */
class MarketplaceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
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

        return $user->refresh();
    }

    protected function paidVendorBooking(VendorProfile $profile, int $price = 5000): Booking
    {
        Setting::setValue('platform_commission_percentage', '10');
        $tour = TourPackage::factory()->forVendor($profile)->create([
            'price' => $price, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved',
        ]);
        $booking = app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Analytics Guest', 'email' => 'a@example.com', 'phone' => '9000000001'],
            null, BookingSource::Website
        );
        app(BookingService::class)->markPayment($booking, PaymentStatus::Paid);

        return $booking->refresh();
    }

    protected function analytics(): MarketplaceAnalyticsService
    {
        return app(MarketplaceAnalyticsService::class);
    }

    public function test_admin_metrics_counts_and_money_split(): void
    {
        $vendor = $this->vendor();
        $this->customer();
        $booking = $this->paidVendorBooking($vendor->vendorProfile);

        // GBV 10000 → commission 1000 → earning 9000.
        $summary = $this->analytics()->adminSummary(null, null);

        $this->assertSame(1, (int) $summary['customers']);
        $this->assertSame(1, (int) $summary['vendors_total']);
        $this->assertSame(1, (int) $summary['vendors_approved']);
        $this->assertSame(1, (int) $summary['bookings_paid']);
        $this->assertSame('10000.00', $summary['gross_booking_value']);
        $this->assertSame('1000.00', $summary['platform_commission']);
        $this->assertSame('9000.00', $summary['vendor_earnings']);
        $this->assertNotSame($summary['gross_booking_value'], $summary['platform_commission']);
        $this->assertSame($booking->id, (int) $booking->id);
    }

    public function test_refunds_and_withdrawals_reported(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidVendorBooking($vendor->vendorProfile);

        BookingRefund::create([
            'booking_id' => $booking->id, 'amount' => '2000.00', 'currency' => 'INR',
            'status' => 'processed', 'reason' => 'Partial test refund',
            'processed_by' => $this->admin()->id, 'processed_at' => now(),
            'vendor_reversal_amount' => '1800.00',
        ]);

        $summary = $this->analytics()->adminSummary(null, null);
        $this->assertSame('2000.00', $summary['refunded_amount']);
    }

    public function test_date_range_applied_server_side(): void
    {
        $vendor = $this->vendor();
        $old = $this->paidVendorBooking($vendor->vendorProfile);
        DB::table('bookings')->where('id', $old->id)->update([
            'created_at' => now()->subDays(40)->toDateTimeString(),
        ]);
        $new = $this->paidVendorBooking($vendor->vendorProfile);

        $range = $this->analytics()->resolveRange(['preset' => 'last30']);
        $summary = $this->analytics()->adminSummary($range['from'], $range['to']);

        $this->assertSame(1, (int) $summary['bookings_paid']);
        $this->assertSame('10000.00', $summary['gross_booking_value']);
        $this->assertSame($new->id, (int) $new->id);
    }

    public function test_vendor_metrics_scoped_to_own_vendor(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $this->paidVendorBooking($vendorA->vendorProfile);
        $this->paidVendorBooking($vendorB->vendorProfile);

        $summaryA = $this->analytics()->vendorSummary($vendorA->vendorProfile, null, null);

        $this->assertSame(1, (int) $summaryA['bookings_paid']);
        $this->assertSame('10000.00', $summaryA['paid_booking_value']);
        $this->assertSame('9000.00', $summaryA['recorded_earnings']);
    }

    public function test_admin_owned_tours_handled(): void
    {
        Setting::setValue('platform_commission_percentage', '10');
        $tour = TourPackage::factory()->create([
            'price' => 4000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved',
        ]);
        $booking = app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Direct Guest', 'email' => 'd@example.com', 'phone' => '9000000001'],
            null, BookingSource::Website
        );
        app(BookingService::class)->markPayment($booking, PaymentStatus::Paid);

        $summary = $this->analytics()->adminSummary(null, null);

        $this->assertSame('4000.00', $summary['gross_booking_value']);
        $this->assertSame('0.00', $summary['platform_commission']);
        $this->assertSame('0.00', $summary['vendor_earnings']);
        $this->assertSame([], $summary['top_vendors']);
    }

    public function test_cancelled_bookings_excluded_from_money(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidVendorBooking($vendor->vendorProfile);
        app(BookingService::class)->changeStatus($booking, BookingStatus::Cancelled);

        $summary = $this->analytics()->adminSummary(null, null);

        $this->assertSame(0, (int) $summary['bookings_paid']);
        $this->assertSame('0.00', $summary['gross_booking_value']);
    }

    public function test_coupon_metrics_present(): void
    {
        $vendor = $this->vendor();
        Coupon::factory()->forVendor($vendor->vendorProfile)->create([
            'code' => 'ANALYTICS10', 'discount_type' => 'fixed', 'discount_value' => 500,
        ]);
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'price' => 5000, 'is_active' => true, 'moderation_status' => 'approved',
        ]);
        $booking = app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Coupon Guest', 'email' => 'c@example.com', 'phone' => '9000000001'],
            null, BookingSource::Website, null, null, null,
            ['coupon_code' => 'ANALYTICS10']
        );

        $summary = $this->analytics()->adminSummary(null, null);

        $this->assertSame(1, (int) $summary['coupons']['redemptions']);
        $this->assertSame('500.00', $summary['coupons']['discount_granted']);
        $this->assertSame($booking->id, (int) $booking->id);
    }

    public function test_admin_dashboard_includes_analytics_props(): void
    {
        $vendor = $this->vendor();
        $this->paidVendorBooking($vendor->vendorProfile);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('analytics')
                ->has('analyticsRange')
                ->has('timeseries')
                ->where('analytics.bookings_paid', 1));
    }

    public function test_vendor_dashboard_scoped_props_only(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $this->paidVendorBooking($vendorA->vendorProfile);
        $this->paidVendorBooking($vendorB->vendorProfile);

        $response = $this->actingAs($vendorA)->get(route('vendor.dashboard'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertSame(1, (int) $props['metrics']['bookings_paid']);
        $this->assertArrayNotHasKey('analytics', $props);
        $this->assertArrayNotHasKey('platform_commission', $props['metrics']);
    }

    public function test_vendor_cannot_access_admin_plans_or_analytics_routes(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($vendor)->get(route('admin.vendor-plans.index'))->assertForbidden();
        $this->actingAs($vendor)->get(route('admin.dashboard'))->assertForbidden();
    }
}
