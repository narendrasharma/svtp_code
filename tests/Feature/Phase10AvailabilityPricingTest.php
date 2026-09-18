<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\TourAddon;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingService;
use App\Services\InvoiceService;
use App\Services\TourAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 10: availability + integrated pricing + security.
 */
class Phase10AvailabilityPricingTest extends TestCase
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

        return $user;
    }

    protected function tour(array $overrides = []): TourPackage
    {
        return TourPackage::factory()->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function bookOn(TourPackage $tour, string $date, array $extras = []): Booking
    {
        return app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => $date],
            ['name' => 'Avail Guest', 'email' => 'avail@example.com', 'phone' => '9000000001'],
            null,
            BookingSource::Website,
            null, null, null,
            $extras
        );
    }

    protected function nextWeekday(int $weekday): string
    {
        $date = Carbon::today()->addDay();

        while ((int) $date->dayOfWeek !== $weekday) {
            $date->addDay();
        }

        return $date->toDateString();
    }

    public function test_existing_tour_defaults_remain_bookable(): void
    {
        $tour = $this->tour();

        $this->assertTrue($tour->booking_enabled ?? true);
        $booking = $this->bookOn($tour, now()->addWeek()->toDateString());
        $this->assertNotNull($booking->id);
    }

    public function test_allowed_weekday_bookable(): void
    {
        $tour = $this->tour(['available_weekdays' => [1, 2, 3, 4, 5]]);
        $monday = $this->nextWeekday(1);

        $this->assertTrue(app(TourAvailabilityService::class)->isBookableOn($tour, $monday));
        $booking = $this->bookOn($tour, $monday);
        $this->assertNotNull($booking->id);
    }

    public function test_unavailable_weekday_rejected(): void
    {
        $tour = $this->tour(['available_weekdays' => [1, 2, 3, 4, 5]]);
        $sunday = $this->nextWeekday(0);

        $this->assertFalse(app(TourAvailabilityService::class)->isBookableOn($tour, $sunday));

        $this->expectException(ValidationException::class);
        $this->bookOn($tour, $sunday);
    }

    public function test_blackout_date_rejected(): void
    {
        $tour = $this->tour();
        $date = now()->addWeek()->toDateString();
        $tour->blackoutDates()->create(['date' => $date, 'reason' => 'Festival']);

        $this->assertFalse(app(TourAvailabilityService::class)->isBookableOn($tour, $date));

        $this->expectException(ValidationException::class);
        $this->bookOn($tour, $date);
    }

    public function test_past_date_rejected(): void
    {
        $tour = $this->tour();

        $this->expectException(ValidationException::class);
        $this->bookOn($tour, now()->subDay()->toDateString());
    }

    public function test_min_advance_enforced(): void
    {
        $tour = $this->tour(['min_advance_days' => 5]);

        $this->expectException(ValidationException::class);
        $this->bookOn($tour, now()->addDays(2)->toDateString());
    }

    public function test_max_advance_enforced(): void
    {
        $tour = $this->tour(['max_advance_days' => 30]);

        $ok = $this->bookOn($tour, now()->addDays(10)->toDateString());
        $this->assertNotNull($ok->id);

        $this->expectException(ValidationException::class);
        $this->bookOn($tour, now()->addDays(60)->toDateString());
    }

    public function test_final_booking_revalidates_availability(): void
    {
        $tour = $this->tour();
        $date = now()->addWeek()->toDateString();

        // Estimate passes while the date is free.
        $this->postJson(route('booking.estimate'), [
            'package_id' => $tour->id, 'total_adults' => 2, 'travel_date' => $date,
        ])->assertOk()->assertJsonPath('availability.bookable', true);

        // Blackout added before submit → booking rejected.
        $tour->blackoutDates()->create(['date' => $date]);

        $this->post(route('booking.store'), [
            'package_id' => $tour->id,
            'travel_date' => $date,
            'total_adults' => 2,
            'customer_name' => 'Late Guest',
            'customer_phone' => '9000000001',
        ])->assertSessionHasErrors('travel_date');
    }

    public function test_blackout_ownership_boundaries(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $tourA = TourPackage::factory()->forVendor($vendorA->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);
        $tourB = TourPackage::factory()->forVendor($vendorB->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);

        // Vendor manages own blackout.
        $this->actingAs($vendorA)->post(route('vendor.tours.blackouts.store', $tourA), [
            'date' => now()->addWeek()->toDateString(), 'reason' => 'Maintenance',
        ])->assertRedirect();
        $this->assertDatabaseHas('tour_blackout_dates', ['tour_package_id' => $tourA->id]);

        // Vendor cannot touch another vendor's tour.
        $this->actingAs($vendorA)->post(route('vendor.tours.blackouts.store', $tourB), [
            'date' => now()->addWeek()->toDateString(),
        ])->assertForbidden();

        // Admin can manage any tour.
        $this->actingAs($this->admin())->post(route('admin.packages.blackouts.store', $tourB), [
            'date' => now()->addWeeks(2)->toDateString(),
        ])->assertRedirect();

        // Customer blocked everywhere.
        $this->actingAs($this->customer())->post(route('admin.packages.blackouts.store', $tourA), [
            'date' => now()->addWeeks(3)->toDateString(),
        ])->assertForbidden();
        $this->actingAs($this->customer())->get(route('vendor.tours.availability.show', $tourA))->assertForbidden();
    }

    public function test_pricing_order_base_addons_then_coupon(): void
    {
        $tour = $this->tour(['price' => 5000]);
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'fixed', 'price' => 1000]);
        Coupon::factory()->create(['code' => 'TEN', 'discount_type' => 'percentage', 'discount_value' => 10]);

        // Tour base 10000 (2 adults) + 1000 addon = 11000 subtotal → 10% = 1100 → 9900 total.
        $booking = $this->bookOn($tour, now()->addWeek()->toDateString(), [
            'addons' => [['addon_id' => $addon->id]],
            'coupon_code' => 'TEN',
        ]);

        $this->assertSame('1000.00', $booking->addons_total);
        $this->assertSame(11000.0, (float) $booking->subtotal);
        $this->assertSame(1100.0, (float) $booking->discount_amount);
        $this->assertSame(9900.0, (float) $booking->total_amount);
        $this->assertSame('9900.00', $booking->gross_amount);
    }

    public function test_admin_owned_tour_discount_without_commission(): void
    {
        $tour = $this->tour(['price' => 5000]);
        Coupon::factory()->create(['code' => 'TEN', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $booking = $this->bookOn($tour, now()->addWeek()->toDateString(), ['coupon_code' => 'TEN']);

        $this->assertNull($booking->vendor_profile_id);
        $this->assertSame(9000.0, (float) $booking->total_amount);
        $this->assertSame('9000.00', $booking->gross_amount);
        $this->assertSame('0.00', $booking->platform_commission_amount);
        $this->assertSame('0.00', $booking->vendor_earning_amount);
    }

    public function test_historical_snapshots_immutable(): void
    {
        $tour = $this->tour(['price' => 5000]);
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'name' => 'Guide', 'pricing_type' => 'fixed', 'price' => 400]);
        $coupon = Coupon::factory()->create(['code' => 'KEEP', 'discount_type' => 'fixed', 'discount_value' => 500]);

        $booking = $this->bookOn($tour, now()->addWeek()->toDateString(), [
            'addons' => [['addon_id' => $addon->id]],
            'coupon_code' => 'KEEP',
        ]);

        $addon->update(['price' => 9999, 'name' => 'Changed']);
        $coupon->update(['discount_value' => 1]);
        $tour->update(['price' => 99999]);

        $booking->refresh();
        $this->assertSame(10400.0, (float) $booking->subtotal);
        $this->assertSame(500.0, (float) $booking->discount_amount);
        $this->assertSame(9900.0, (float) $booking->total_amount);
        $this->assertSame('Guide', $booking->bookingAddons->first()->name);
    }

    public function test_vendor_booking_detail_shows_addons_and_coupon(): void
    {
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['price' => 5000, 'is_active' => true, 'moderation_status' => 'approved']);
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'name' => 'Guide', 'pricing_type' => 'fixed', 'price' => 300]);
        Coupon::factory()->forVendor($vendor->vendorProfile)->create(['code' => 'V10', 'discount_type' => 'fixed', 'discount_value' => 200]);

        $booking = app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Vendor Guest', 'email' => 'v@example.com', 'phone' => '9000000001'],
            null, BookingSource::Website, null, null, null,
            ['addons' => [['addon_id' => $addon->id]], 'coupon_code' => 'V10']
        );

        $response = $this->actingAs($vendor)->get(route('vendor.bookings.show', $booking));
        $response->assertOk();
        $props = $response->viewData('page')['props']['booking'];
        $this->assertCount(1, $props['addons']);
        $this->assertSame('Guide', $props['addons'][0]['name']);
        $this->assertSame('V10', $props['pricing']['coupon_code']);
    }

    public function test_invoice_shows_addons_and_discount(): void
    {
        $tour = $this->tour(['price' => 5000]);
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'name' => 'Pickup', 'pricing_type' => 'fixed', 'price' => 500]);
        Coupon::factory()->create(['code' => 'INV5', 'discount_type' => 'fixed', 'discount_value' => 500]);

        $booking = $this->bookOn($tour, now()->addWeek()->toDateString(), [
            'addons' => [['addon_id' => $addon->id]], 'coupon_code' => 'INV5',
        ]);

        $receipt = app(InvoiceService::class)->receiptData($booking);
        $this->assertSame('500.00', $receipt['pricing']['addons_total']);
        $this->assertSame('INV5', $receipt['pricing']['coupon_code']);
        $this->assertSame('500.00', $receipt['pricing']['discount_amount']);
        $this->assertSame('10000.00', $receipt['pricing']['total_amount']);
    }

    public function test_impersonated_vendor_scoped_normally_for_catalog(): void
    {
        $admin = $this->admin();
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $tourA = TourPackage::factory()->forVendor($vendorA->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);
        $tourB = TourPackage::factory()->forVendor($vendorB->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);

        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendorA))->assertRedirect();

        $this->get(route('vendor.coupons.index'))->assertOk();
        $this->get(route('vendor.tours.addons.index', $tourA))->assertOk();
        $this->get(route('vendor.tours.addons.index', $tourB))->assertForbidden();
        $this->get(route('vendor.tours.availability.show', $tourB))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_estimate_includes_detailed_breakdown(): void
    {
        $tour = $this->tour(['price' => 5000]);
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'fixed', 'price' => 500]);
        Coupon::factory()->create(['code' => 'EST10', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $response = $this->postJson(route('booking.estimate'), [
            'package_id' => $tour->id,
            'total_adults' => 2,
            'addons' => [['addon_id' => $addon->id]],
            'coupon_code' => 'EST10',
            'travel_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertOk();
        // 10000 tour + 500 addon = 10500 subtotal → 1050 discount → 9450 total.
        $this->assertSame(10000.0, (float) $response->json('tour_base'));
        $this->assertSame(500.0, (float) $response->json('addons_total'));
        $this->assertSame(10500.0, (float) $response->json('subtotal'));
        $this->assertSame(1050.0, (float) $response->json('discount_amount'));
        $this->assertSame(9450.0, (float) $response->json('total_amount'));
    }
}
