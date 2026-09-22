<?php

namespace Tests\Feature\Hotel;

use App\Enums\HotelBookingStatus;
use App\Models\HotelBookingCancellation;
use App\Models\HotelBookingRefund;
use App\Models\User;
use App\Services\HotelBookingChangeService;
use App\Services\HotelBookingService;
use App\Services\HotelPricingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class HotelCancellationRefundTest extends HotelBookingTest
{
    public function test_cancellation_releases_history_and_retry_is_idempotent(): void
    {
        Notification::fake();
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-12', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
        $service = app(HotelBookingChangeService::class);
        $first = $service->cancel($booking, 'cancel-once', $customer, 'customer_request', '<b>requested</b>');
        $second = $service->cancel($booking, 'cancel-once', $customer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(HotelBookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(1, HotelBookingCancellation::count());
        $this->assertSame(2, $booking->reservationNights()->count());
        $this->assertStringNotContainsString('<b>', (string) $first->note);
        $this->assertSame('unpaid', $booking->fresh()->payment_status->value);
    }

    public function test_refund_is_capped_and_retry_does_not_create_another_record(): void
    {
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
        $booking->update(['amount_paid' => '80.00']);
        $cancellation = app(HotelBookingChangeService::class)->cancel($booking, 'cancel-refund', $customer);
        $service = app(HotelBookingChangeService::class);
        $first = $service->createRefund($booking, 'refund-once', $customer, $cancellation);
        $second = $service->createRefund($booking, 'refund-once', $customer, $cancellation);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HotelBookingRefund::count());
        $this->assertLessThanOrEqual(80, (float) $first->amount);
        $this->assertSame('unpaid', $booking->fresh()->payment_status->value);
    }

    public function test_cancellation_quote_uses_snapshot_after_rate_plan_changes(): void
    {
        [$property, $room, $plan] = $this->hotel(1);
        $customer = User::factory()->create();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-04-10', '2027-04-11', 1, 2, 0);
        $booking = app(HotelBookingService::class)->create($this->payload($room, $plan, $quote), $customer, $customer);
        $plan->update(['cancellation_mode' => 'non_refundable']);

        $policy = app(HotelBookingChangeService::class)->cancellationQuote($booking, Carbon::parse('2027-04-01', $property->timezone ?: config('app.timezone')));

        $this->assertSame('flexible', $policy['policy_summary']['mode']);
    }
}
