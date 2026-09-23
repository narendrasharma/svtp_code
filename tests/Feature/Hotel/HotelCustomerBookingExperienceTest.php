<?php

namespace Tests\Feature\Hotel;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\HotelBookingService;
use App\Services\HotelPricingService;
use App\Support\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

class HotelCustomerBookingExperienceTest extends HotelBookingTest
{
    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleManager::class)->setEnabled('hotels', true);
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_customer_booking_list_is_owner_scoped_and_compact(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $owner = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $owner, $owner);
        app(HotelBookingService::class)->create($this->payload($room, $plan, $quote + ['check_in' => '2027-04-15', 'check_out' => '2027-04-17']), $otherCustomer, $otherCustomer);

        $response = $this->actingAs($owner)->page(route('account.hotel-bookings.index', absolute: false));
        $bookings = $response->json('props.bookings.data');

        $this->assertCount(1, $bookings);
        $this->assertSame($booking->booking_number, $bookings[0]['booking_number']);
        $this->assertArrayNotHasKey('pricing_snapshot', $bookings[0]);
        $this->assertArrayNotHasKey('guest_email', $bookings[0]);
    }

    public function test_customer_booking_detail_uses_safe_snapshot_fields_and_server_capabilities(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);

        $response = $this->actingAs($customer)->page(route('account.hotel-bookings.show', $booking, absolute: false));
        $detail = $response->json('props.booking');

        $this->assertSame($booking->booking_number, $detail['booking_number']);
        $this->assertSame('confirmed', $detail['status']);
        $this->assertSame('unpaid', $detail['payment_status']);
        $this->assertArrayNotHasKey('pricing_snapshot', $detail);
        $this->assertSame(true, $detail['actions']['can_cancel']);
        $this->assertSame(true, $detail['actions']['can_reschedule']);
        $this->assertArrayHasKey('timeline', $detail);
        $this->assertArrayHasKey('refunds', $detail);
        $this->assertArrayHasKey('changes', $detail);
    }

    public function test_customer_cannot_open_another_customers_hotel_booking_detail(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $owner, $owner);

        $this->actingAs($intruder)->get(route('account.hotel-bookings.show', $booking, absolute: false))->assertNotFound();
    }

    public function test_customer_change_quotes_expose_display_money_without_raw_pricing_payloads(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);

        $cancellation = $this->actingAs($customer)->getJson(route('account.hotel-bookings.cancellation-quote', $booking, absolute: false));
        $cancellation->assertOk()->assertJsonStructure(['display_money' => ['cancellation_fee', 'remaining_refundable']]);

        $reschedule = $this->actingAs($customer)->postJson(route('account.hotel-bookings.reschedule-quote', $booking, absolute: false), [
            'check_in' => '2027-04-11',
            'check_out' => '2027-04-13',
        ]);

        $reschedule->assertOk()->assertJsonStructure(['quote_fingerprint', 'display_money' => ['old_total', 'new_total', 'difference']])->assertJsonMissingPath('quote');
    }

    private function page(string $url): TestResponse
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create($url));
        $response = $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version]);

        return $response->assertOk();
    }
}
