<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Enums\TripType;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiRateCard;
use App\Models\VehicleType;
use App\Notifications\CrmNotification;
use App\Notifications\TaxiTrackingLinkNotification;
use App\Services\TaxiTrackingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TaxiGuestAccessRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Setting::setValue('modules.taxi.enabled', '1');
    }

    public function test_guest_booking_email_recovers_signed_confirmation_without_booking_lookup(): void
    {
        Notification::fake();
        $vehicleType = $this->vehicleTypeWithPricing();

        $this->post(route('taxi.book', absolute: false), [
            ...$this->tripPayload(),
            'vehicle_type_id' => $vehicleType->id,
            'customer_name' => 'Guest Rider',
            'customer_phone' => '9999999999',
            'customer_email' => 'guest@example.com',
        ])->assertRedirect();

        $booking = TaxiBooking::query()->latest('id')->firstOrFail();
        $guestNotification = null;
        Notification::assertSentOnDemand(CrmNotification::class, function (CrmNotification $notification) use (&$guestNotification): bool {
            $guestNotification = $notification;

            return ($notification->data['guest_recipient'] ?? false) === true
                && isset($notification->data['guest_url'])
                && ! isset($notification->toArray(new AnonymousNotifiable)['meta']['guest_url']);
        });

        $guestUrl = $guestNotification->data['guest_url'];
        $parts = parse_url($guestUrl);
        $path = $parts['path'].'?'.$parts['query'];

        $this->get($path)->assertOk();

        $tamperedPath = '/taxi/confirmation/'.($booking->id + 1).'?'.$parts['query'];
        $this->get($tamperedPath)->assertNotFound();
    }

    public function test_tracking_email_delivers_capability_without_persisting_raw_token(): void
    {
        Notification::fake();
        Setting::setValue('taxi.customer_tracking.enabled', '1');

        $booking = TaxiBooking::factory()->create([
            'customer_email' => 'guest@example.com',
            'customer_user_id' => null,
        ]);
        $generated = app(TaxiTrackingTokenService::class)->generate($booking);

        Notification::assertSentOnDemand(TaxiTrackingLinkNotification::class, function (TaxiTrackingLinkNotification $notification) use ($generated): bool {
            $payload = $notification->toArray(new AnonymousNotifiable);

            return $notification->guestRecipient
                && str_contains($notification->trackingUrl, $generated['token'])
                && ! str_contains(json_encode($payload, JSON_THROW_ON_ERROR), $generated['token']);
        });
    }

    /** @return array<string, mixed> */
    private function tripPayload(): array
    {
        return [
            'trip_type' => TripType::OneWay->value,
            'pickup_at' => now()->addHours(3)->format('Y-m-d\\TH:i'),
            'pickup_address' => 'Pickup address',
            'pickup_lat' => 27.49,
            'pickup_lng' => 77.67,
            'drop_address' => 'Drop address',
            'drop_lat' => 27.50,
            'drop_lng' => 77.68,
            'passenger_count' => 1,
            'luggage_count' => 0,
        ];
    }

    private function vehicleTypeWithPricing(): VehicleType
    {
        $vehicleType = VehicleType::factory()->create(['passenger_capacity' => 4]);
        $card = TaxiRateCard::create([
            'vehicle_type_id' => $vehicleType->id,
            'name' => 'Guest recovery sedan',
            'trip_type' => TripType::OneWay->value,
            'currency' => 'INR',
            'is_active' => true,
        ]);
        $card->rules()->createMany([
            ['code' => TaxiRateRuleCode::BaseFare->value, 'calculation_type' => TaxiRateCalculationType::Fixed->value, 'amount' => 100],
            ['code' => TaxiRateRuleCode::MinimumFare->value, 'calculation_type' => TaxiRateCalculationType::Fixed->value, 'amount' => 100],
        ]);

        return $vehicleType;
    }
}
