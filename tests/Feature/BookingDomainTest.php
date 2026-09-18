<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\Admin\StoreManualBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\BookingReference;
use App\Services\BookingService;
use App\Services\TourBookingPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingDomainTest extends TestCase
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

    protected function tourPackage(array $overrides = []): TourPackage
    {
        return TourPackage::factory()->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true],
            $overrides
        ));
    }

    public function test_reference_format_and_uniqueness(): void
    {
        $this->assertMatchesRegularExpression('/^BK-\d{4}-\d{6}$/', BookingReference::generate());

        $package = $this->tourPackage();
        $travel = ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()];
        $first = $this->service()->createTourBooking($package, $travel, ['name' => 'A', 'phone' => '1'], null, BookingSource::Website);
        $second = $this->service()->createTourBooking($package, $travel, ['name' => 'B', 'phone' => '2'], null, BookingSource::Website);

        $this->assertNotSame($first->booking_reference_id, $second->booking_reference_id);
    }

    public function test_guest_booking_is_allowed_with_customer_snapshot(): void
    {
        $booking = $this->service()->createTourBooking(
            $this->tourPackage(),
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Guest Traveller', 'email' => 'guest@example.com', 'phone' => '9876543210', 'country' => 'India'],
            null,
            BookingSource::Website
        );

        $this->assertNull($booking->user_id);
        $this->assertSame('Guest Traveller', $booking->customer_name);
        $this->assertSame('India', $booking->country);
        $this->assertSame('tour', $booking->product_type);
        $this->assertTrue($booking->isTour());
        $this->assertInstanceOf(TourPackage::class, $booking->package);
    }

    public function test_status_and_payment_casts(): void
    {
        $booking = Booking::factory()->create();

        $this->assertSame(BookingStatus::Pending, $booking->booking_status);
        $this->assertSame(PaymentStatus::Unpaid, $booking->payment_status);
        $this->assertSame(BookingSource::Website, $booking->source);
    }

    public function test_price_snapshot_is_immutable(): void
    {
        $package = $this->tourPackage(['price' => 4000]);
        $booking = Booking::factory()->create(['package_id' => $package->id, 'total_adults' => 2, 'total_children' => 0]);

        $this->assertSame(8000.0, (float) $booking->total_amount);

        $package->update(['price' => 9999]);

        $this->assertSame(8000.0, (float) $booking->refresh()->total_amount);
        $this->assertSame(4000.0, (float) $booking->base_price);
    }

    public function test_server_pricing_uses_current_package_and_ignores_client_totals(): void
    {
        $package = $this->tourPackage(['price' => 5000, 'discounted_price' => 4000]);
        $quote = app(TourBookingPricingService::class)->quote($package, 2, 2);

        // Effective price wins; children are half price; money stays exact.
        $this->assertSame(4000.0, $quote['base_price']);
        $this->assertSame(2000.0, $quote['child_unit_price']);
        $this->assertSame(12000.0, $quote['subtotal']);
        $this->assertSame(12000.0, $quote['total_amount']);
        $this->assertSame('INR', $quote['currency']);

        // Protected fields are never accepted from requests: the service sets
        // them server-side (proven by the forged-total admin test).
        $manualRules = (new StoreManualBookingRequest)->rules();
        $this->assertArrayNotHasKey('total_amount', $manualRules);
        $this->assertArrayNotHasKey('product_type', $manualRules);
        $this->assertArrayNotHasKey('booking_reference_id', $manualRules);
    }

    public function test_money_precision_is_preserved(): void
    {
        $package = $this->tourPackage(['price' => 5000, 'discounted_price' => 3333.33]);
        $booking = Booking::factory()->create(['package_id' => $package->id, 'total_adults' => 2, 'total_children' => 1]);

        $this->assertSame(8333.33, (float) $booking->total_amount);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'total_amount' => 8333.33]);
    }

    public function test_inactive_package_cannot_be_booked(): void
    {
        $package = $this->tourPackage(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->service()->createTourBooking(
            $package,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Guest', 'phone' => '1'],
            null,
            BookingSource::Website
        );
    }

    public function test_product_type_cannot_be_injected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $package = $this->tourPackage();

        // product_type is not part of any booking request and the service
        // hardcodes tours, so a hostile product_type can never take effect.
        $webRules = (new StoreBookingRequest)->rules();
        $this->assertArrayNotHasKey('product_type', $webRules);

        $booking = $this->service()->createTourBooking(
            $package,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Guest', 'phone' => '1'],
            null,
            BookingSource::Website
        );
        $this->assertSame('tour', $booking->product_type);
    }

    public function test_website_booking_validation(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $package = $this->tourPackage();

        // Nonexistent package, past date and invalid guest counts are rejected
        // before any booking or gateway work happens.
        $this->actingAs($customer)->post(route('booking.store'), [
            'package_id' => 999999, 'travel_date' => now()->addWeek()->toDateString(), 'total_adults' => 1,
        ])->assertSessionHasErrors('package_id');

        $this->actingAs($customer)->post(route('booking.store'), [
            'package_id' => $package->id, 'travel_date' => now()->subDay()->toDateString(), 'total_adults' => 1,
        ])->assertSessionHasErrors('travel_date');

        $this->actingAs($customer)->post(route('booking.store'), [
            'package_id' => $package->id, 'travel_date' => now()->addWeek()->toDateString(), 'total_adults' => 0,
        ])->assertSessionHasErrors('total_adults');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_policy_owner_admin_and_guest_visibility(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $stranger = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $owned = Booking::factory()->create(['user_id' => $owner->id]);
        $guest = Booking::factory()->guest()->create();

        // Guests must log in first (asserted before any actingAs in this test,
        // since authentication persists across requests within one test).
        $this->get(route('booking.invoice', $owned))->assertRedirect(route('login'));

        // Owner can view their invoice; strangers cannot.
        $this->actingAs($owner)->get(route('booking.invoice', $owned))->assertOk();
        $this->actingAs($stranger)->get(route('booking.invoice', $owned))->assertForbidden();

        // Admins can view everything, including guest bookings.
        $this->actingAs($admin)->get(route('booking.invoice', $guest))->assertOk();
    }
}
