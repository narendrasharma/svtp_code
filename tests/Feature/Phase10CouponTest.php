<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 10: coupon / promo code system.
 */
class Phase10CouponTest extends TestCase
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
            ['price' => 10000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function vendorTour(VendorProfile $profile, array $overrides = []): TourPackage
    {
        return TourPackage::factory()->forVendor($profile)->create(array_merge(
            ['price' => 10000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function book(TourPackage $tour, array $extras = [], ?User $user = null, ?string $email = 'guest@example.com'): Booking
    {
        return app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Coupon Guest', 'email' => $email, 'phone' => '9000000001'],
            $user,
            BookingSource::Website,
            null, null, null,
            $extras
        );
    }

    public function test_active_percentage_coupon_applies(): void
    {
        $tour = $this->tour();
        Coupon::factory()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $booking = $this->book($tour, ['coupon_code' => 'save10']);

        $this->assertSame(10000.0, (float) $booking->subtotal);
        $this->assertSame(1000.0, (float) $booking->discount_amount);
        $this->assertSame(9000.0, (float) $booking->total_amount);
        $this->assertSame('SAVE10', $booking->coupon_code);
    }

    public function test_fixed_coupon_applies(): void
    {
        $tour = $this->tour();
        Coupon::factory()->create(['code' => 'FLAT500', 'discount_type' => 'fixed', 'discount_value' => 500]);

        $booking = $this->book($tour, ['coupon_code' => 'FLAT500']);

        $this->assertSame(500.0, (float) $booking->discount_amount);
        $this->assertSame(9500.0, (float) $booking->total_amount);
    }

    public function test_expired_future_inactive_rejected(): void
    {
        $tour = $this->tour();
        Coupon::factory()->expired()->create(['code' => 'OLD']);
        Coupon::factory()->upcoming()->create(['code' => 'SOON']);
        Coupon::factory()->inactive()->create(['code' => 'OFF']);

        foreach (['OLD', 'SOON', 'OFF'] as $code) {
            try {
                $this->book($tour, ['coupon_code' => $code]);
                $this->fail("Coupon {$code} should be rejected");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('coupon_code', $e->errors());
            }
        }
    }

    public function test_minimum_amount_enforced(): void
    {
        $tour = $this->tour(['price' => 1000]);
        Coupon::factory()->create(['code' => 'BIG', 'discount_type' => 'percentage', 'discount_value' => 10, 'minimum_booking_amount' => 5000]);

        $this->expectException(ValidationException::class);
        $this->book($tour, ['coupon_code' => 'BIG']);
    }

    public function test_maximum_discount_cap_works(): void
    {
        $tour = $this->tour();
        Coupon::factory()->create(['code' => 'CAPPED', 'discount_type' => 'percentage', 'discount_value' => 50, 'maximum_discount_amount' => 1000]);

        $booking = $this->book($tour, ['coupon_code' => 'CAPPED']);

        $this->assertSame(1000.0, (float) $booking->discount_amount);
        $this->assertSame(9000.0, (float) $booking->total_amount);
    }

    public function test_global_usage_limit_enforced(): void
    {
        $tour = $this->tour();
        Coupon::factory()->create(['code' => 'ONCE', 'discount_type' => 'fixed', 'discount_value' => 100, 'usage_limit' => 1]);

        $this->book($tour, ['coupon_code' => 'ONCE'], null, 'first@example.com');

        $this->expectException(ValidationException::class);
        $this->book($tour, ['coupon_code' => 'ONCE'], null, 'second@example.com');
    }

    public function test_per_user_limit_enforced_for_customer(): void
    {
        $tour = $this->tour();
        $customer = $this->customer();
        Coupon::factory()->create(['code' => 'PERUSER', 'discount_type' => 'fixed', 'discount_value' => 100, 'usage_limit_per_user' => 1]);

        $this->book($tour, ['coupon_code' => 'PERUSER'], $customer, $customer->email);

        $this->expectException(ValidationException::class);
        $this->book($tour, ['coupon_code' => 'PERUSER'], $customer, $customer->email);
    }

    public function test_guest_email_usage_tracked(): void
    {
        $tour = $this->tour();
        Coupon::factory()->create(['code' => 'GUEST1', 'discount_type' => 'fixed', 'discount_value' => 100, 'usage_limit_per_user' => 1]);

        $first = $this->book($tour, ['coupon_code' => 'GUEST1'], null, 'Guest@Example.com');
        $this->assertSame('guest@example.com', $first->couponRedemption->email);

        $this->expectException(ValidationException::class);
        $this->book($tour, ['coupon_code' => 'GUEST1'], null, 'guest@example.com');
    }

    public function test_vendor_coupon_only_own_tour(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $tourA = $this->vendorTour($vendorA->vendorProfile);
        $tourB = $this->vendorTour($vendorB->vendorProfile);

        Coupon::factory()->forVendor($vendorA->vendorProfile)->create(['code' => 'VENDORA']);

        $ok = $this->book($tourA, ['coupon_code' => 'VENDORA']);
        $this->assertSame('VENDORA', $ok->coupon_code);

        $this->expectException(ValidationException::class);
        $this->book($tourB, ['coupon_code' => 'VENDORA']);
    }

    public function test_admin_global_coupon_works_on_vendor_tour(): void
    {
        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);
        Coupon::factory()->create(['code' => 'GLOBAL10', 'discount_type' => 'percentage', 'discount_value' => 10, 'scope' => 'global']);

        $booking = $this->book($tour, ['coupon_code' => 'GLOBAL10']);

        $this->assertSame(1000.0, (float) $booking->discount_amount);
        $this->assertSame(9000.0, (float) $booking->total_amount);
    }

    public function test_coupon_snapshot_preserved_after_edit(): void
    {
        $tour = $this->tour();
        $coupon = Coupon::factory()->create(['code' => 'SNAP', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $booking = $this->book($tour, ['coupon_code' => 'SNAP']);
        $coupon->update(['discount_value' => 50, 'code' => 'CHANGED']);

        $booking->refresh();
        $this->assertSame('SNAP', $booking->coupon_code);
        $this->assertSame('percentage', $booking->coupon_discount_type);
        $this->assertSame('10.00', $booking->coupon_discount_value);
        $this->assertSame(1000.0, (float) $booking->discount_amount);
        $this->assertSame(9000.0, (float) $booking->total_amount);
    }

    public function test_frontend_cannot_submit_discount_amount(): void
    {
        $tour = $this->tour();

        $this->post(route('booking.store'), [
            'package_id' => $tour->id,
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 1,
            'customer_name' => 'Sneaky',
            'customer_phone' => '9000000001',
            'discount_amount' => '9999',
            'total_amount' => '1',
            'coupon_id' => 999,
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();
        $this->assertSame(0.0, (float) $booking->discount_amount);
        $this->assertSame(10000.0, (float) $booking->total_amount);
        $this->assertNull($booking->coupon_id);
    }

    public function test_coupon_revalidated_on_final_booking_after_deactivation(): void
    {
        $tour = $this->tour();
        $coupon = Coupon::factory()->create(['code' => 'RACE', 'discount_type' => 'fixed', 'discount_value' => 100]);

        // Preview succeeds.
        $preview = $this->postJson(route('booking.estimate'), [
            'package_id' => $tour->id,
            'total_adults' => 1,
            'coupon_code' => 'RACE',
        ])->assertOk();
        $this->assertSame(100.0, (float) $preview->json('discount_amount'));

        // Deactivated before submit → final booking rejected.
        $coupon->update(['is_active' => false]);

        $this->post(route('booking.store'), [
            'package_id' => $tour->id,
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 1,
            'customer_name' => 'Late Guest',
            'customer_phone' => '9000000002',
            'coupon_code' => 'RACE',
        ])->assertSessionHasErrors('coupon_code');
    }

    public function test_total_never_negative_with_huge_fixed_coupon(): void
    {
        $tour = $this->tour(['price' => 500]);
        Coupon::factory()->create(['code' => 'HUGE', 'discount_type' => 'fixed', 'discount_value' => 99999]);

        $booking = $this->book($tour, ['coupon_code' => 'HUGE']);

        $this->assertSame(500.0, (float) $booking->discount_amount);
        $this->assertSame(0.0, (float) $booking->total_amount);
    }

    public function test_commission_uses_discounted_gross(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);
        Coupon::factory()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $booking = $this->book($tour, ['coupon_code' => 'SAVE10']);

        // 10000 − 10% = 9000 gross → 900 commission / 8100 earning.
        $this->assertSame('9000.00', $booking->gross_amount);
        $this->assertSame('900.00', $booking->platform_commission_amount);
        $this->assertSame('8100.00', $booking->vendor_earning_amount);
    }

    public function test_admin_coupon_crud_and_vendor_boundaries(): void
    {
        $admin = $this->admin();
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();

        // Admin creates a global coupon.
        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'ADMIN10', 'name' => 'Admin Ten', 'discount_type' => 'percentage',
            'discount_value' => 10, 'scope' => 'global', 'is_active' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'ADMIN10', 'scope' => 'global']);

        // Vendor creates own coupon.
        $this->actingAs($vendorA)->post(route('vendor.coupons.store'), [
            'code' => 'MYSHOP', 'name' => 'My Shop', 'discount_type' => 'fixed',
            'discount_value' => 200, 'scope' => 'vendor', 'is_active' => true,
        ])->assertRedirect();
        $coupon = Coupon::where('code', 'MYSHOP')->first();
        $this->assertSame($vendorA->vendorProfile->id, (int) $coupon->vendor_profile_id);

        // Vendor cannot create a global coupon.
        $this->actingAs($vendorA)->post(route('vendor.coupons.store'), [
            'code' => 'GLOBALHACK', 'name' => 'Hack', 'discount_type' => 'fixed',
            'discount_value' => 5, 'scope' => 'global',
        ])->assertSessionHasErrors('scope');

        // Vendor B cannot edit vendor A's coupon.
        $this->actingAs($vendorB)->put(route('vendor.coupons.update', $coupon), [
            'code' => 'MYSHOP', 'name' => 'Hijacked', 'discount_type' => 'fixed',
            'discount_value' => 1, 'scope' => 'vendor',
        ])->assertForbidden();

        // Customer cannot manage coupons.
        $this->actingAs($this->customer())->get(route('admin.coupons.index'))->assertForbidden();
        $this->actingAs($this->customer())->get(route('vendor.coupons.index'))->assertForbidden();

        // Coupon with redemption history cannot be hard-deleted.
        $tour = $this->vendorTour($vendorA->vendorProfile);
        $this->book($tour, ['coupon_code' => 'MYSHOP'], null, 'history@example.com');
        $this->actingAs($vendorA)->delete(route('vendor.coupons.destroy', $coupon))->assertSessionHasErrors('coupon');
        $this->assertDatabaseHas('coupons', ['code' => 'MYSHOP']);
    }
}
