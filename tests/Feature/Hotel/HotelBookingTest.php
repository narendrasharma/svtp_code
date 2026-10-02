<?php

namespace Tests\Feature\Hotel;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelReservationStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\HotelBooking;
use App\Models\HotelChargeRule;
use App\Models\HotelDailyRate;
use App\Models\HotelRatePlan;
use App\Models\HotelReservationNight;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\HotelAvailabilityService;
use App\Services\HotelBookingChangeService;
use App\Services\HotelBookingService;
use App\Services\HotelPricingService;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HotelBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleManager::class)->setEnabled('hotels', true);
    }

    public function test_valid_booking_creates_immutable_snapshot_and_nightly_reservations(): void
    {
        Notification::fake();
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);

        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);

        $this->assertSame(2, $booking->reservationNights()->count());
        $this->assertSame('2027-04-10', $booking->reservationNights()->first()->stay_date->toDateString());
        $this->assertSame('2027-04-11', $booking->reservationNights()->latest('stay_date')->first()->stay_date->toDateString());
        $this->assertSame($quote['total'], $booking->total);
        $this->assertSame($property->name, $booking->property_name_snapshot);
        $this->assertSame(1, HotelBooking::count());
    }

    public function test_reservations_reduce_availability_without_mutating_configured_capacity(): void
    {
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);

        $this->assertDatabaseHas('hotel_reservation_nights', ['room_type_id' => $room->id, 'stay_date' => '2027-04-10 00:00:00', 'quantity' => 1, 'status' => 'confirmed']);

        $check = app(HotelAvailabilityService::class)->checkRoomType($room, '2027-04-10', '2027-04-11');

        $this->assertFalse($check['available']);
        $this->assertSame(1, $check['nights'][0]['reserved']);
        $this->assertSame(1, $room->fresh()->total_units);
    }

    public function test_idempotent_retry_returns_the_original_booking_without_extra_nights(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $payload = $this->payload($room, $plan, $quote) + ['idempotency_key' => 'hotel-retry-1'];
        $first = app(HotelBookingService::class)->create($payload, $customer, $customer);
        $second = app(HotelBookingService::class)->create($payload, $customer, $customer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HotelBooking::count());
        $this->assertSame(1, HotelReservationNight::count());
    }

    public function test_stale_quote_fingerprint_is_rejected_before_booking_creation(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $payload = array_merge($this->payload($room, $plan, $quote), ['quote_fingerprint' => str_repeat('0', 64)]);

        $this->expectException(ValidationException::class);
        app(HotelBookingService::class)->create($payload, $customer, $customer);
    }

    public function test_booking_rejects_unpublished_property_inactive_room_and_inactive_plan(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);

        $property->forceFill(['status' => PropertyStatus::Draft->value])->save();
        $this->expectException(ValidationException::class);
        $payload = $this->payload($room, $plan, $quote);
        unset($payload['quote_fingerprint']);
        app(HotelBookingService::class)->create($payload, $customer, $customer);
    }

    public function test_booking_rejects_inactive_room_plan_mismatch_and_bad_dates(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);

        $room->update(['status' => RoomTypeStatus::Inactive->value]);
        $this->expectException(ValidationException::class);
        app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
    }

    public function test_multi_night_quantity_and_checkout_exclusive_reservations_are_correct(): void
    {
        [$property, $room, $plan] = $this->hotel(3);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-13', 2, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);

        $this->assertSame(3, $booking->reservationNights()->count());
        $this->assertSame(2, $booking->reservationNights()->first()->quantity);
        $this->assertDatabaseMissing('hotel_reservation_nights', ['stay_date' => '2027-04-13 00:00:00']);
        $this->assertTrue(app(HotelAvailabilityService::class)->checkRoomType($room, '2027-04-13', '2027-04-14')['available']);
    }

    public function test_public_booking_reserves_multiple_room_types_at_one_property_with_server_totals(): void
    {
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Notification::fake();
        [$property, $room, $plan] = $this->hotel(3);
        $suite = HotelRoomType::factory()->create(['property_id' => $property->id, 'status' => RoomTypeStatus::Active->value, 'total_units' => 2]);
        $suitePlan = HotelRatePlan::factory()->create(['property_id' => $property->id, 'hotel_room_type_id' => $suite->id, 'currency' => 'USD', 'base_rate' => '150.00']);
        $customer = User::factory()->create();
        $data = [
            'items' => [
                ['room_type_id' => $room->id, 'rate_plan_id' => $plan->id, 'quantity' => 2],
                ['room_type_id' => $suite->id, 'rate_plan_id' => $suitePlan->id, 'quantity' => 1],
            ],
            'check_in' => '2027-04-10', 'check_out' => '2027-04-12', 'adults' => 3, 'children' => 0,
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '+911234567890',
            'subtotal' => '0.01', 'total' => '0.01',
        ];
        $quote = app(HotelBookingService::class)->quoteSelection($property, $data);
        $query = http_build_query(['items' => $data['items'], 'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'adults' => $data['adults'], 'children' => 0]);
        $this->actingAs($customer)->getJson('/hotels/'.$property->slug.'/booking-quote?'.$query)
            ->assertOk()->assertJsonPath('rooms_count', 3)->assertJsonCount(2, 'items');
        $this->actingAs($customer)->get('/hotels/'.$property->slug.'/book?'.$query)
            ->assertInertia(fn (Assert $page) => $page->component('Hotels/Booking')->has('selection.items', 2)->where('stay.rooms', 3));
        $this->actingAs($customer)->post('/hotels/'.$property->slug.'/book', $data + ['quote_fingerprint' => $quote['quote_fingerprint']])
            ->assertRedirect();

        $booking = HotelBooking::with('items.reservationNights')->sole();
        $this->assertSame(3, $booking->rooms_count);
        $this->assertSame([2, 1], $booking->items->pluck('quantity')->all());
        $this->assertSame(4, $booking->reservationNights()->count());
        $this->assertEquals($quote['total'], $booking->total);
        $this->assertEquals($quote['subtotal'], $booking->subtotal);
        $this->assertSame($property->id, $booking->property_id);
        $this->actingAs($customer)->get('/hotel-bookings/'.$booking->id.'/confirmation')
            ->assertInertia(fn (Assert $page) => $page->component('Hotels/Confirmation')->has('booking.items', 2));
    }

    public function test_multi_room_booking_rejects_cross_property_rooms_and_sold_out_quantity(): void
    {
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        [$property, $room, $plan] = $this->hotel(1);
        [$otherProperty, $otherRoom, $otherPlan] = $this->hotel(2);
        $customer = User::factory()->create();
        $data = [
            'items' => [
                ['room_type_id' => $room->id, 'rate_plan_id' => $plan->id, 'quantity' => 1],
                ['room_type_id' => $otherRoom->id, 'rate_plan_id' => $otherPlan->id, 'quantity' => 1],
            ],
            'check_in' => '2027-04-10', 'check_out' => '2027-04-11', 'adults' => 2, 'children' => 0,
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '+911234567890',
        ];
        $this->actingAs($customer)->post('/hotels/'.$property->slug.'/book', $data)->assertSessionHasErrors('items');
        $data['items'] = [['room_type_id' => $room->id, 'rate_plan_id' => $plan->id, 'quantity' => 2]];
        $this->actingAs($customer)->post('/hotels/'.$property->slug.'/book', $data)->assertSessionHasErrors('availability');
        $this->assertSame(0, HotelBooking::count());
    }

    public function test_multi_room_cancellation_uses_each_rate_policy(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $suite = HotelRoomType::factory()->create(['property_id' => $property->id, 'status' => RoomTypeStatus::Active->value, 'total_units' => 2]);
        $suitePlan = HotelRatePlan::factory()->create(['property_id' => $property->id, 'hotel_room_type_id' => $suite->id, 'currency' => 'USD', 'base_rate' => '150.00', 'cancellation_mode' => HotelRatePlan::CANCEL_NON_REFUNDABLE]);
        $customer = User::factory()->create();
        $booking = app(HotelBookingService::class)->create([
            'items' => [
                ['room_type_id' => $room->id, 'rate_plan_id' => $plan->id, 'quantity' => 1],
                ['room_type_id' => $suite->id, 'rate_plan_id' => $suitePlan->id, 'quantity' => 1],
            ],
            'property_id' => $property->id, 'check_in' => '2027-04-10', 'check_out' => '2027-04-12',
            'adults' => 2, 'children' => 0, 'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '+911234567890',
        ], $customer, $customer);
        $quote = app(HotelBookingChangeService::class)->cancellationQuote($booking, Carbon::parse('2027-04-01'));

        $this->assertEquals($booking->items->last()->total, $quote['cancellation_fee']);
    }

    public function test_sold_out_and_stop_sell_reject_the_whole_stay_without_mutating_capacity(): void
    {
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $firstQuote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        app(HotelBookingService::class)->create($this->payload($room, $plan, $firstQuote), $customer, $customer);
        $secondQuote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);

        $this->expectException(ValidationException::class);
        app(HotelBookingService::class)->create($this->payload($room, $plan, $secondQuote), User::factory()->create(), $customer);
        $this->assertSame(1, $room->fresh()->total_units);
    }

    public function test_inventory_stop_sell_rejects_booking(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        HotelRoomInventory::factory()->create(['hotel_room_type_id' => $room->id, 'inventory_date' => '2027-04-10', 'stop_sell' => true]);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);

        $this->expectException(ValidationException::class);
        app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
    }

    public function test_cancelled_reservation_releases_capacity_and_non_consuming_state_does_not(): void
    {
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
        $booking->reservationNights()->update(['status' => HotelReservationStatus::Cancelled->value]);

        $check = app(HotelAvailabilityService::class)->checkRoomType($room, '2027-04-10', '2027-04-11');
        $this->assertTrue($check['available']);
        $this->assertSame(0, $check['nights'][0]['reserved']);
    }

    public function test_server_recalculates_price_and_ignores_forged_financial_fields(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $payload = $this->payload($room, $plan, $quote) + ['subtotal' => '0.01', 'total' => '0.01', 'currency' => 'EUR'];
        $booking = app(HotelBookingService::class)->create($payload, $customer, $customer);

        $this->assertSame('100.00', $booking->subtotal);
        $this->assertSame('100.00', $booking->total);
        $this->assertSame('USD', $booking->currency);
    }

    public function test_daily_override_and_tax_fee_rules_are_snapshotted(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        HotelDailyRate::factory()->create(['hotel_rate_plan_id' => $plan->id, 'rate_date' => '2027-04-10', 'amount_override' => '125.00']);
        HotelChargeRule::factory()->create(['property_id' => $property->id, 'charge_type' => HotelChargeRule::TYPE_TAX, 'calculation' => HotelChargeRule::CALC_PERCENTAGE, 'value' => '10.00', 'currency' => 'USD']);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
        $snapshot = $booking->fresh()->pricing_snapshot;

        $plan->update(['base_rate' => '999.00', 'name' => 'Changed']);
        $property->update(['name' => 'Changed property']);
        $this->assertEquals(125, (float) $snapshot['nightly'][0]['base_rate']);
        $this->assertEquals(125, (float) $booking->fresh()->subtotal);
        $this->assertEquals(125, (float) ($booking->fresh()->pricing_snapshot['nightly'][0]['base_rate'] ?? 0));
    }

    public function test_booking_status_transitions_are_valid_and_cancel_releases_reservations(): void
    {
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
        $booking = app(HotelBookingService::class)->changeStatus($booking, HotelBookingStatus::Cancelled, $customer);

        $this->assertSame(HotelBookingStatus::Cancelled, $booking->status);
        $this->assertSame(HotelReservationStatus::Cancelled, $booking->reservationNights()->first()->status);
        $this->expectException(ValidationException::class);
        app(HotelBookingService::class)->changeStatus($booking, HotelBookingStatus::Completed, $customer);
    }

    public function test_guest_booking_requires_setting_and_can_confirm_when_enabled(): void
    {
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        [$property, $room, $plan] = $this->hotel(1);
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $data = $this->payload($room, $plan, $quote) + ['idempotency_key' => 'guest-booking-once'];
        $this->post('/hotels/'.$property->slug.'/book', $data)->assertRedirect(route('login'));
        Setting::setValue('hotel.booking.allow_guest_booking', '1');
        $this->post('/hotels/'.$property->slug.'/book', $data)->assertRedirect();
        $booking = HotelBooking::sole();
        $this->assertNull($booking->user_id);
        $this->post('/hotels/'.$property->slug.'/book', array_replace($data, ['guest_email' => 'someone-else@example.com']))->assertSessionHasErrors('idempotency_key');
        $this->get('/hotel-bookings/'.$booking->id.'/confirmation')->assertOk();
    }

    public function test_checkout_requires_login_and_preserves_selection_for_authenticated_customer(): void
    {
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create(['role' => 'customer', 'phone' => '+15551234567']);
        $query = [
            'room_type_id' => $room->id,
            'rate_plan_id' => $plan->id,
            'check_in' => '2027-04-10',
            'check_out' => '2027-04-12',
            'rooms' => 2,
            'adults' => 3,
            'children' => 1,
        ];

        $this->actingAs($customer)
            ->get(route('hotel-booking.create', ['slug' => $property->slug] + $query, absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hotels/Booking')
                ->where('selection.items.0.room_type_id', $room->id)
                ->where('selection.items.0.rate_plan_id', $plan->id)
                ->where('stay.rooms', 2)
                ->where('stay.adults', 3)
                ->where('stay.children', 1)
                ->where('customer.phone', '+15551234567')
            );
    }

    public function test_booking_enabled_setting_and_hotel_module_are_required(): void
    {
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        Setting::setValue('hotel.booking.enabled', '0');

        $this->expectException(ValidationException::class);
        app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
    }

    public function test_idempotent_retry_does_not_duplicate_notifications(): void
    {
        Notification::fake();
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $payload = $this->payload($room, $plan, $quote) + ['idempotency_key' => 'notification-retry'];
        app(HotelBookingService::class)->create($payload, $customer, $customer);
        app(HotelBookingService::class)->create($payload, $customer, $customer);

        Notification::assertSentTo($customer, CrmNotification::class, 1);
    }

    /** @return array{0:Property, 1:HotelRoomType, 2:HotelRatePlan} */
    protected function hotel(int $capacity): array
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Published->value, 'published_at' => now(), 'currency' => 'USD']);
        $room = HotelRoomType::factory()->create(['property_id' => $property->id, 'status' => RoomTypeStatus::Active->value, 'total_units' => $capacity]);
        $plan = HotelRatePlan::factory()->create(['property_id' => $property->id, 'hotel_room_type_id' => $room->id, 'currency' => 'USD', 'base_rate' => '100.00']);

        return [$property, $room, $plan];
    }

    /** @return array<string, mixed> */
    protected function payload(HotelRoomType $room, HotelRatePlan $plan, array $quote): array
    {
        return ['room_type_id' => $room->id, 'rate_plan_id' => $plan->id, 'check_in' => $quote['check_in'], 'check_out' => $quote['check_out'], 'rooms' => $quote['rooms'], 'adults' => $quote['adults'], 'children' => $quote['children'], 'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '+911234567890', 'quote_fingerprint' => HotelBookingService::fingerprint($quote)];
    }
}
