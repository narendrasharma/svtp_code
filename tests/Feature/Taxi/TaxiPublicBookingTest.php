<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Enums\TripType;
use App\Models\Setting;
use App\Models\TaxiRateCard;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TaxiPublicBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Setting::setValue('modules.taxi.enabled', '1');
    }

    public function test_public_quote_returns_server_priced_vehicle_option(): void
    {
        $vehicleType = $this->vehicleTypeWithPricing();

        $response = $this->postJson(route('taxi.quote', absolute: false), [
            ...$this->tripPayload(),
            'passenger_count' => 2,
        ]);

        $response->assertOk()->assertJsonPath('options.0.vehicle.id', $vehicleType->id)->assertJsonPath('options.0.total.amount', '100.00');
    }

    public function test_public_guest_booking_uses_server_quote_and_signed_confirmation(): void
    {
        $vehicleType = $this->vehicleTypeWithPricing();

        $response = $this->post(route('taxi.book', absolute: false), [
            ...$this->tripPayload(),
            'vehicle_type_id' => $vehicleType->id,
            'customer_name' => 'Guest Rider',
            'customer_phone' => '9999999999',
            'total_amount' => '1.00',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/taxi/confirmation/', $response->headers->get('Location'));
        $this->assertDatabaseHas('taxi_bookings', [
            'source' => 'website',
            'customer_name' => 'Guest Rider',
            'vehicle_type_id' => $vehicleType->id,
            'total_amount' => '100.00',
            'pickup_lat' => '27.49',
            'drop_lng' => '77.68',
        ]);
    }

    public function test_public_taxi_flow_respects_module_gating(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $this->get(route('taxi.enquiry', absolute: false))->assertNotFound();
        $this->postJson(route('taxi.quote', absolute: false), $this->tripPayload())->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function tripPayload(): array
    {
        return [
            'trip_type' => TripType::OneWay->value,
            'pickup_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
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
            'name' => 'Public sedan',
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
