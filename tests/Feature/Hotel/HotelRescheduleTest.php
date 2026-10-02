<?php

namespace Tests\Feature\Hotel;

use App\Enums\RoomTypeStatus;
use App\Models\HotelBookingChange;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\User;
use App\Services\HotelBookingChangeService;
use App\Services\HotelBookingService;
use App\Services\HotelPricingService;

class HotelRescheduleTest extends HotelBookingTest
{
    public function test_overlap_excludes_own_allocation_and_preserves_change_history_on_retry(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
        $service = app(HotelBookingChangeService::class);
        $rescheduleQuote = $service->rescheduleQuote($booking, '2027-04-11', '2027-04-13');
        $change = $service->reschedule($booking, 'reschedule-once', '2027-04-11', '2027-04-13', $customer, $rescheduleQuote['quote_fingerprint']);
        $retry = $service->reschedule($booking, 'reschedule-once', '2027-04-11', '2027-04-13', $customer, $rescheduleQuote['quote_fingerprint']);

        $this->assertSame($change->id, $retry->id);
        $this->assertSame(1, HotelBookingChange::count());
        $this->assertSame(['2027-04-10'], $booking->reservationNights()->where('hotel_reservation_nights.status', 'cancelled')->pluck('hotel_reservation_nights.stay_date')->map->toDateString()->all());
        $this->assertSame(['2027-04-11', '2027-04-12'], $booking->reservationNights()->where('hotel_reservation_nights.status', 'confirmed')->pluck('hotel_reservation_nights.stay_date')->map->toDateString()->all());
        $this->assertSame('2027-04-11', $booking->fresh()->check_in->toDateString());
        $this->assertDatabaseMissing('hotel_reservation_nights', ['stay_date' => '2027-04-13 00:00:00', 'status' => 'confirmed']);
    }

    public function test_multi_room_reschedule_moves_all_room_type_reservations_and_totals(): void
    {
        [$property, $room, $plan] = $this->hotel(2);
        $suite = HotelRoomType::factory()->create(['property_id' => $property->id, 'status' => RoomTypeStatus::Active->value, 'total_units' => 2]);
        $suitePlan = HotelRatePlan::factory()->create(['property_id' => $property->id, 'hotel_room_type_id' => $suite->id, 'currency' => 'USD', 'base_rate' => '150.00']);
        $customer = User::factory()->create();
        $booking = app(HotelBookingService::class)->create([
            'items' => [
                ['room_type_id' => $room->id, 'rate_plan_id' => $plan->id, 'quantity' => 1],
                ['room_type_id' => $suite->id, 'rate_plan_id' => $suitePlan->id, 'quantity' => 1],
            ],
            'property_id' => $property->id, 'check_in' => '2027-04-10', 'check_out' => '2027-04-12',
            'adults' => 2, 'children' => 0, 'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '+911234567890',
        ], $customer, $customer);
        $service = app(HotelBookingChangeService::class);
        $quote = $service->rescheduleQuote($booking, '2027-04-11', '2027-04-13');
        $service->reschedule($booking, 'multi-reschedule-once', '2027-04-11', '2027-04-13', $customer, $quote['quote_fingerprint']);

        $this->assertSame(2, $booking->items()->count());
        $this->assertSame(4, $booking->reservationNights()->where('hotel_reservation_nights.status', 'confirmed')->count());
        $this->assertSame(2, $booking->reservationNights()->where('hotel_reservation_nights.status', 'cancelled')->count());
        $this->assertEquals($quote['new_total'], $booking->fresh()->total);
    }
}
