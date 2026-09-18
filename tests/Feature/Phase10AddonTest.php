<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Models\Booking;
use App\Models\TourAddon;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 10: tour add-ons / extras.
 */
class Phase10AddonTest extends TestCase
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

    protected function book(TourPackage $tour, array $addons = []): Booking
    {
        return app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Addon Guest', 'email' => 'addon@example.com', 'phone' => '9000000001'],
            null,
            BookingSource::Website,
            null, null, null,
            ['addons' => $addons]
        );
    }

    public function test_fixed_addon_calculation(): void
    {
        $tour = $this->tour();
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'fixed', 'price' => 500]);

        $booking = $this->book($tour, [['addon_id' => $addon->id, 'quantity' => 1]]);

        // Tour base 10000 + 500 = 10500 subtotal, no discount.
        $this->assertSame('500.00', $booking->addons_total);
        $this->assertSame(10500.0, (float) $booking->subtotal);
        $this->assertSame(10500.0, (float) $booking->total_amount);
        $this->assertCount(1, $booking->bookingAddons);
    }

    public function test_per_person_addon_calculation(): void
    {
        $tour = $this->tour();
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'per_person', 'price' => 200]);

        $booking = $this->book($tour, [['addon_id' => $addon->id]]);

        // 2 guests × 200 = 400.
        $this->assertSame('400.00', $booking->addons_total);
        $this->assertSame(10400.0, (float) $booking->subtotal);
    }

    public function test_per_quantity_addon_calculation(): void
    {
        $tour = $this->tour();
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'per_quantity', 'price' => 150, 'max_quantity' => 5]);

        $booking = $this->book($tour, [['addon_id' => $addon->id, 'quantity' => 3]]);

        $this->assertSame('450.00', $booking->addons_total);
        $this->assertSame(10450.0, (float) $booking->subtotal);
    }

    public function test_max_quantity_validation(): void
    {
        $tour = $this->tour();
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'per_quantity', 'price' => 100, 'max_quantity' => 2]);

        $this->expectException(ValidationException::class);
        $this->book($tour, [['addon_id' => $addon->id, 'quantity' => 5]]);
    }

    public function test_required_addon_auto_applied(): void
    {
        $tour = $this->tour();
        TourAddon::factory()->required()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'fixed', 'price' => 300]);

        $booking = $this->book($tour, []);

        $this->assertSame('300.00', $booking->addons_total);
        $this->assertCount(1, $booking->bookingAddons);
    }

    public function test_addon_from_wrong_tour_rejected(): void
    {
        $tourA = $this->tour();
        $tourB = $this->tour();
        $foreign = TourAddon::factory()->create(['tour_package_id' => $tourB->id, 'price' => 100]);

        $this->expectException(ValidationException::class);
        $this->book($tourA, [['addon_id' => $foreign->id]]);
    }

    public function test_frontend_price_manipulation_ignored(): void
    {
        $tour = $this->tour();
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'pricing_type' => 'fixed', 'price' => 500]);

        // HTTP layer only accepts addon_id/quantity — no price field exists,
        // so extra payload keys are ignored and DB price wins.
        $this->post(route('booking.store'), [
            'package_id' => $tour->id,
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 2,
            'customer_name' => 'Price Hacker',
            'customer_phone' => '9000000001',
            'addons' => [['addon_id' => $addon->id, 'quantity' => 1, 'price' => 1, 'unit_price' => 1]],
            'subtotal' => '1',
            'total_amount' => '1',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();
        $this->assertSame('500.00', $booking->addons_total);
        $this->assertSame(10500.0, (float) $booking->total_amount);
    }

    public function test_booking_snapshot_immutable_after_addon_edit(): void
    {
        $tour = $this->tour();
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'name' => 'Old Name', 'pricing_type' => 'fixed', 'price' => 500]);

        $booking = $this->book($tour, [['addon_id' => $addon->id]]);

        $addon->update(['name' => 'New Name', 'price' => 9999]);
        $addon->delete();

        $booking->refresh();
        $this->assertSame(10500.0, (float) $booking->subtotal);
        $this->assertSame('Old Name', $booking->bookingAddons->first()->name);
        $this->assertSame('500.00', $booking->bookingAddons->first()->unit_price);
    }

    public function test_vendor_owns_only_own_tour_addons(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $tourA = TourPackage::factory()->forVendor($vendorA->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);
        $tourB = TourPackage::factory()->forVendor($vendorB->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);

        // Vendor A can manage own tour.
        $this->actingAs($vendorA)->post(route('vendor.tours.addons.store', $tourA), [
            'name' => 'Guide', 'pricing_type' => 'fixed', 'price' => 400,
        ])->assertRedirect();
        $this->assertDatabaseHas('tour_addons', ['tour_package_id' => $tourA->id, 'name' => 'Guide']);

        // Vendor A cannot manage vendor B's tour.
        $this->actingAs($vendorA)->post(route('vendor.tours.addons.store', $tourB), [
            'name' => 'Hijack', 'pricing_type' => 'fixed', 'price' => 10,
        ])->assertForbidden();
        $this->assertDatabaseMissing('tour_addons', ['name' => 'Hijack']);

        // Admin can manage any tour.
        $this->actingAs($this->admin())->post(route('admin.packages.addons.store', $tourB), [
            'name' => 'Admin Extra', 'pricing_type' => 'fixed', 'price' => 250,
        ])->assertRedirect();
        $this->assertDatabaseHas('tour_addons', ['tour_package_id' => $tourB->id, 'name' => 'Admin Extra']);

        // Customer cannot manage add-ons.
        $this->actingAs($this->customer())->post(route('admin.packages.addons.store', $tourA), [
            'name' => 'Nope', 'pricing_type' => 'fixed', 'price' => 10,
        ])->assertForbidden();
    }

    public function test_url_tampering_blocked_for_addons(): void
    {
        $vendor = $this->vendor();
        $other = $this->vendor();
        $tourOwn = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);
        $tourOther = TourPackage::factory()->forVendor($other->vendorProfile)->create(['is_active' => true, 'moderation_status' => 'approved']);
        $foreign = TourAddon::factory()->create(['tour_package_id' => $tourOther->id]);

        // Mismatched tour/addon pair → 404, never cross-writes.
        $this->actingAs($vendor)->put(route('vendor.tours.addons.update', [$tourOwn->id, $foreign->id]), [
            'name' => 'Hacked', 'pricing_type' => 'fixed', 'price' => 1,
        ])->assertNotFound();
        $this->assertSame($foreign->name, $foreign->refresh()->name);
    }

    public function test_invoice_and_vendor_detail_show_addons(): void
    {
        $tour = $this->tour();
        $addon = TourAddon::factory()->create(['tour_package_id' => $tour->id, 'name' => 'Airport Pickup', 'pricing_type' => 'fixed', 'price' => 500]);

        $booking = $this->book($tour, [['addon_id' => $addon->id]]);

        $receipt = app(InvoiceService::class)->receiptData($booking);
        $this->assertCount(1, $receipt['addons']);
        $this->assertSame('Airport Pickup', $receipt['addons'][0]['name']);
        $this->assertSame('500.00', $receipt['addons'][0]['total_amount']);
    }
}
