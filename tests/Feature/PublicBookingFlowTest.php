<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function tourPackage(array $overrides = []): TourPackage
    {
        return TourPackage::factory()->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true],
            $overrides
        ));
    }

    protected function guestPayload(TourPackage $package, array $overrides = []): array
    {
        return array_merge([
            'package_id' => $package->id,
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 2,
            'total_children' => 1,
            'customer_name' => 'Guest Traveller',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '9876543210',
            'country' => 'India',
        ], $overrides);
    }

    protected function signedConfirmation(Booking $booking): string
    {
        // Absolute, like the controller redirect: the signed middleware
        // validates against the absolute URL, so relative signatures fail.
        return URL::signedRoute('booking.confirmation', $booking);
    }

    public function test_active_tour_opens_the_booking_form_for_guests(): void
    {
        $package = $this->tourPackage();

        $this->get(route('booking.form', ['package' => $package->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Create')
                ->where('package.id', $package->id)
                ->where('customer', null)
            );
    }

    public function test_inactive_or_missing_tour_cannot_open_the_form(): void
    {
        $package = $this->tourPackage(['is_active' => false]);

        $this->get(route('booking.form', ['package' => $package->slug]))->assertNotFound();
        $this->get('/packages/no-such-tour/book')->assertNotFound();
    }

    public function test_logged_in_form_prefills_customer_details(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'name' => 'Prefilled User', 'phone' => '9111111111']);
        $package = $this->tourPackage();

        $this->actingAs($user)
            ->get(route('booking.form', ['package' => $package->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.name', 'Prefilled User')
                ->where('customer.phone', '9111111111')
            );
    }

    public function test_travel_date_and_traveller_validation(): void
    {
        $package = $this->tourPackage();

        $this->post(route('booking.store'), $this->guestPayload($package, ['travel_date' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('travel_date');

        $this->post(route('booking.store'), $this->guestPayload($package, ['total_adults' => 0]))
            ->assertSessionHasErrors('total_adults');

        $this->post(route('booking.store'), $this->guestPayload($package, ['total_adults' => 31]))
            ->assertSessionHasErrors('total_adults');

        $this->post(route('booking.store'), $this->guestPayload($package, ['package_id' => 999999]))
            ->assertSessionHasErrors('package_id');

        $this->post(route('booking.store'), $this->guestPayload($package, ['customer_email' => 'not-an-email']))
            ->assertSessionHasErrors('customer_email');

        $this->post(route('booking.store'), $this->guestPayload($package, ['customer_name' => null, 'customer_phone' => null]))
            ->assertSessionHasErrors(['customer_name', 'customer_phone']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_inactive_package_cannot_be_booked(): void
    {
        $package = $this->tourPackage(['is_active' => false]);

        $this->post(route('booking.store'), $this->guestPayload($package))
            ->assertSessionHasErrors('package_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_estimate_comes_from_the_server_pricing_service(): void
    {
        $package = $this->tourPackage(['price' => 5000, 'discounted_price' => 4000]);

        $response = $this->postJson(route('booking.estimate'), [
            'package_id' => $package->id, 'total_adults' => 2, 'total_children' => 2,
        ])->assertOk();

        // Effective price wins; children half price.
        $response->assertJsonPath('base_price', 4000)
            ->assertJsonPath('child_unit_price', 2000)
            ->assertJsonPath('subtotal', 12000)
            ->assertJsonPath('total_amount', 12000)
            ->assertJsonPath('currency', 'INR');

        $this->postJson(route('booking.estimate'), [
            'package_id' => $this->tourPackage(['is_active' => false])->id, 'total_adults' => 1,
        ])->assertUnprocessable();
    }

    public function test_guest_booking_creates_snapshot_and_redirects_to_signed_confirmation(): void
    {
        $package = $this->tourPackage();

        // Forged totals, user, source and statuses must all be ignored.
        $response = $this->post(route('booking.store'), $this->guestPayload($package, [
            'total_amount' => 1, 'user_id' => 999, 'source' => 'admin',
            'booking_status' => 'completed', 'payment_status' => 'paid',
        ]));

        $booking = Booking::sole();
        $response->assertRedirect($this->signedConfirmation($booking));

        $this->assertNull($booking->user_id);
        $this->assertSame(12500.0, (float) $booking->total_amount);
        $this->assertSame(5000.0, (float) $booking->base_price);
        $this->assertSame('pending', $booking->booking_status->value);
        $this->assertSame('unpaid', $booking->payment_status->value);
        $this->assertSame('website', $booking->source->value);
        $this->assertSame('Guest Traveller', $booking->customer_name);
        $this->assertSame('India', $booking->country);
        $this->assertMatchesRegularExpression('/^BK-\d{4}-\d{6}$/', $booking->booking_reference_id);
        $this->assertSame(1, $booking->statusHistories()->count());

        $this->followRedirects($response)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Confirmation')
                ->where('booking.booking_reference_id', $booking->booking_reference_id)
                ->where('booking.total_amount', 12500)
            );
    }

    public function test_guest_confirmation_requires_a_valid_signature(): void
    {
        $booking = Booking::factory()->guest()->create();

        // No signature, tampered signature, or cross-booking signature: all rejected.
        $this->get("/bookings/confirmation/{$booking->id}")->assertForbidden();

        $other = Booking::factory()->guest()->create();
        $this->get($this->signedConfirmation($other))->assertOk();
        $tampered = $this->signedConfirmation($booking).'&tampered=1';
        $this->get($tampered)->assertForbidden();

        $crossUrl = str_replace(
            "/bookings/confirmation/{$other->id}?",
            "/bookings/confirmation/{$booking->id}?",
            $this->signedConfirmation($other)
        );
        $this->get($crossUrl)->assertForbidden();

        // Reference alone reveals nothing.
        $this->get("/bookings/confirmation/{$booking->id}?reference={$booking->booking_reference_id}")->assertForbidden();
    }

    public function test_authenticated_booking_links_the_customer_and_stays_private(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $stranger = User::factory()->create(['role' => 'customer']);
        $package = $this->tourPackage();

        // Submitted user_id is ignored; the authenticated user always wins.
        $this->actingAs($owner)->post(route('booking.store'), $this->guestPayload($package, ['user_id' => $stranger->id]));
        $booking = Booking::sole();
        $this->assertSame($owner->id, $booking->user_id);

        // Owner opens the signed confirmation; strangers without it cannot.
        $this->actingAs($owner)->get($this->signedConfirmation($booking))->assertOk();
        $this->actingAs($stranger)->get($this->signedConfirmation($booking))->assertOk(); // bearer link, same as emailed link
        $this->actingAs($stranger)->get("/bookings/confirmation/{$booking->id}")->assertForbidden();
    }

    public function test_payment_handoff_uses_the_stored_snapshot_total(): void
    {
        $booking = Booking::factory()->guest()->create();
        $payUrl = URL::signedRoute('booking.pay', $booking);

        // The pay endpoint accepts no money fields; the page carries the snapshot.
        $this->post($payUrl, ['total_amount' => 1])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Payment')
                ->where('booking.total_amount', fn ($total) => (float) $total === (float) $booking->total_amount)
                ->where('order', null)
            );
    }

    public function test_pay_is_unavailable_once_booking_leaves_pending_unpaid(): void
    {
        $booking = Booking::factory()->guest()->confirmed()->create(['payment_status' => 'paid']);
        $payUrl = URL::signedRoute('booking.pay', $booking);

        $response = $this->post($payUrl);
        $response->assertRedirect($this->signedConfirmation($booking));
        $this->followRedirects($response)->assertInertia(fn (Assert $page) => $page->component('Booking/Confirmation'));
    }

    public function test_public_booking_routes_respect_the_base_path(): void
    {
        $package = $this->tourPackage();

        $this->assertStringStartsWith('/packages/', route('booking.form', ['package' => $package->slug], false));
        $this->get(route('booking.form', ['package' => $package->slug]))->assertOk();
    }
}
