<?php

namespace Tests\Feature\Hotel;

use App\Models\HotelBookingChange;
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
}
