<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Enums\TripType;
use App\Http\Requests\Taxi\StoreTaxiBookingRequest;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiRateCard;
use App\Models\TaxiRentalPackage;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use App\Services\TaxiPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_rate_card_stores_typed_rules_and_rental_packages(): void
    {
        $vendor = VendorProfile::factory()->create();
        $vehicleType = VehicleType::factory()->create();
        $rateCard = TaxiRateCard::create([
            'vendor_profile_id' => $vendor->id,
            'vehicle_type_id' => $vehicleType->id,
            'name' => 'Vendor hourly sedan',
            'trip_type' => TripType::Hourly->value,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $rule = $rateCard->rules()->create([
            'code' => TaxiRateRuleCode::Tax->value,
            'calculation_type' => TaxiRateCalculationType::Percentage->value,
            'amount' => '7.5000',
        ]);
        $package = $rateCard->rentalPackages()->create([
            'name' => 'Four hours',
            'included_hours' => '4.00',
            'included_km' => '40.00',
            'package_price' => '120.00',
            'extra_km_rate' => '1.2500',
            'extra_hour_rate' => '20.0000',
        ]);

        $this->assertSame(TripType::Hourly, $rateCard->fresh()->trip_type);
        $this->assertSame(TaxiRateRuleCode::Tax, $rule->fresh()->code);
        $this->assertSame(TaxiRateCalculationType::Percentage, $rule->fresh()->calculation_type);
        $this->assertSame('7.5000', $rule->fresh()->amount);
        $this->assertSame('4.00', $package->fresh()->included_hours);
        $this->assertTrue($rateCard->fresh()->vendorProfile->is($vendor));
        $this->assertTrue($rateCard->fresh()->vehicleType->is($vehicleType));
    }

    public function test_booking_snapshot_survives_soft_deleted_pricing_records(): void
    {
        $rateCard = TaxiRateCard::create([
            'name' => 'Platform default',
            'trip_type' => TripType::OneWay->value,
            'currency' => 'EUR',
            'is_active' => true,
        ]);
        $package = TaxiRentalPackage::create([
            'taxi_rate_card_id' => $rateCard->id,
            'name' => 'Local package',
            'included_hours' => '2.00',
            'included_km' => '20.00',
            'package_price' => '60.00',
            'extra_km_rate' => '1.0000',
            'extra_hour_rate' => '15.0000',
        ]);
        $booking = TaxiBooking::factory()->create();
        $booking->forceFill([
            'taxi_rate_card_id' => $rateCard->id,
            'taxi_rental_package_id' => $package->id,
            'pricing_snapshot' => [
                'version' => 1,
                'currency' => 'EUR',
                'grand_total' => '60.00',
            ],
            'priced_at' => now(),
        ])->save();

        $package->delete();
        $rateCard->delete();

        $snapshot = $booking->fresh()->pricing_snapshot;
        $this->assertSame(1, $snapshot['version']);
        $this->assertSame('EUR', $snapshot['currency']);
        $this->assertSame('60.00', $snapshot['grand_total']);
    }

    public function test_distance_pricing_applies_base_minimum_included_and_extra_kilometers(): void
    {
        $card = $this->rateCard(TripType::OneWay);
        $this->rule($card, TaxiRateRuleCode::BaseFare, '50');
        $this->rule($card, TaxiRateRuleCode::MinimumFare, '100');
        $this->rule($card, TaxiRateRuleCode::DistanceRate, '10', TaxiRateCalculationType::PerKilometer, '5');
        $this->rule($card, TaxiRateRuleCode::ExtraDistanceRate, '15', TaxiRateCalculationType::PerKilometer, null, [
            'starts_after_km' => 20,
        ]);

        $quote = app(TaxiPricingService::class)->quote([
            'trip_type' => TripType::OneWay->value,
            'currency' => 'USD',
            'distance_km' => '25',
        ]);

        $this->assertSame('50.00', $quote['breakdown']['base_fare']);
        $this->assertSame('150.00', $quote['breakdown']['distance_charge']);
        $this->assertSame('75.00', $quote['breakdown']['extra_km_charge']);
        $this->assertSame('0.00', $quote['breakdown']['minimum_fare_adjustment']);
        $this->assertSame('275.00', $quote['breakdown']['grand_total']);
    }

    public function test_minimum_fare_increases_a_short_trip(): void
    {
        $card = $this->rateCard(TripType::OneWay);
        $this->rule($card, TaxiRateRuleCode::BaseFare, '20');
        $this->rule($card, TaxiRateRuleCode::MinimumFare, '75');
        $this->rule($card, TaxiRateRuleCode::DistanceRate, '5', TaxiRateCalculationType::PerKilometer, '10');

        $quote = app(TaxiPricingService::class)->quote([
            'trip_type' => TripType::OneWay->value,
            'currency' => 'USD',
            'distance_km' => '4',
        ]);

        $this->assertSame('55.00', $quote['breakdown']['minimum_fare_adjustment']);
        $this->assertSame('75.00', $quote['breakdown']['grand_total']);
    }

    public function test_rate_card_resolution_uses_vendor_and_vehicle_precedence_without_merging_rules(): void
    {
        $vendor = VendorProfile::factory()->create();
        $vehicleType = VehicleType::factory()->create();
        $platformDefault = $this->rateCard(TripType::AirportTransfer);
        $platformVehicle = $this->rateCard(TripType::AirportTransfer, null, $vehicleType);
        $vendorDefault = $this->rateCard(TripType::AirportTransfer, $vendor);
        $vendorVehicle = $this->rateCard(TripType::AirportTransfer, $vendor, $vehicleType);
        $this->rule($platformDefault, TaxiRateRuleCode::BaseFare, '10');
        $this->rule($platformVehicle, TaxiRateRuleCode::BaseFare, '20');
        $this->rule($vendorDefault, TaxiRateRuleCode::BaseFare, '30');
        $this->rule($vendorVehicle, TaxiRateRuleCode::BaseFare, '40');
        $this->rule($platformDefault, TaxiRateRuleCode::Tax, '50', TaxiRateCalculationType::Percentage);

        $pricing = app(TaxiPricingService::class);
        $vendorVehicleQuote = $pricing->quote([
            'trip_type' => TripType::AirportTransfer->value,
            'currency' => 'USD',
            'vendor_profile_id' => $vendor->id,
            'vehicle_type_id' => $vehicleType->id,
        ]);
        $vendorDefaultQuote = $pricing->quote([
            'trip_type' => TripType::AirportTransfer->value,
            'currency' => 'USD',
            'vendor_profile_id' => $vendor->id,
        ]);
        $platformVehicleQuote = $pricing->quote([
            'trip_type' => TripType::AirportTransfer->value,
            'currency' => 'USD',
            'vehicle_type_id' => $vehicleType->id,
        ]);

        $this->assertSame($vendorVehicle->id, $vendorVehicleQuote['rate_card']->id);
        $this->assertSame('40.00', $vendorVehicleQuote['breakdown']['grand_total']);
        $this->assertSame($vendorDefault->id, $vendorDefaultQuote['rate_card']->id);
        $this->assertSame($platformVehicle->id, $platformVehicleQuote['rate_card']->id);
        $this->assertSame('0.00', $vendorVehicleQuote['breakdown']['tax']);
    }

    public function test_hourly_package_calculates_extra_distance_and_time(): void
    {
        $card = $this->rateCard(TripType::Hourly);
        $package = $card->rentalPackages()->create([
            'name' => 'Four hour local',
            'included_hours' => '4',
            'included_km' => '40',
            'package_price' => '100',
            'extra_km_rate' => '2',
            'extra_hour_rate' => '20',
        ]);

        $quote = app(TaxiPricingService::class)->quote([
            'trip_type' => TripType::Hourly->value,
            'currency' => 'USD',
            'rental_package_id' => $package->id,
            'distance_km' => '50',
            'duration_minutes' => 330,
        ]);

        $this->assertSame('100.00', $quote['breakdown']['rental_charge']);
        $this->assertSame('20.00', $quote['breakdown']['extra_km_charge']);
        $this->assertSame('30.00', $quote['breakdown']['extra_hour_charge']);
        $this->assertSame('150.00', $quote['breakdown']['grand_total']);
    }

    public function test_round_trip_applies_daily_minimum_driver_night_waiting_and_tax(): void
    {
        $card = $this->rateCard(TripType::RoundTrip);
        $this->rule($card, TaxiRateRuleCode::BaseFare, '50');
        $this->rule($card, TaxiRateRuleCode::DistanceRate, '1', TaxiRateCalculationType::PerKilometer, null, [
            'minimum_km' => 100,
            'minimum_km_basis' => 'day',
        ]);
        $this->rule($card, TaxiRateRuleCode::DriverAllowance, '25', TaxiRateCalculationType::PerDay);
        $this->rule($card, TaxiRateRuleCode::NightCharge, '10', TaxiRateCalculationType::Percentage, null, [
            'start_time' => '22:00',
            'end_time' => '06:00',
        ]);
        $this->rule($card, TaxiRateRuleCode::WaitingCharge, '12', TaxiRateCalculationType::PerHour);
        $this->rule($card, TaxiRateRuleCode::Tax, '10', TaxiRateCalculationType::Percentage);

        $quote = app(TaxiPricingService::class)->quote([
            'trip_type' => TripType::RoundTrip->value,
            'currency' => 'USD',
            'pickup_at' => '2026-10-01 23:00:00',
            'return_at' => '2026-10-03 11:00:00',
            'distance_km' => '150',
            'waiting_minutes' => 30,
        ]);

        $this->assertSame('200.00', $quote['breakdown']['distance_charge']);
        $this->assertSame('50.00', $quote['breakdown']['driver_allowance']);
        $this->assertSame('25.00', $quote['breakdown']['night_charge']);
        $this->assertSame('6.00', $quote['breakdown']['waiting_charge']);
        $this->assertSame('33.10', $quote['breakdown']['tax']);
        $this->assertSame('364.10', $quote['breakdown']['grand_total']);
    }

    public function test_actual_toll_and_parking_require_authorized_operational_input(): void
    {
        $card = $this->rateCard(TripType::Outstation);
        $this->rule($card, TaxiRateRuleCode::BaseFare, '100');
        $this->rule($card, TaxiRateRuleCode::Toll, '0', TaxiRateCalculationType::Actual);
        $this->rule($card, TaxiRateRuleCode::Parking, '0', TaxiRateCalculationType::Actual);
        $input = [
            'trip_type' => TripType::Outstation->value,
            'currency' => 'USD',
            'pickup_at' => '2026-10-01 08:00:00',
            'return_at' => '2026-10-01 20:00:00',
            'toll_amount' => '12.50',
            'parking_amount' => '5.25',
        ];

        try {
            app(TaxiPricingService::class)->quote($input);
            $this->fail('Unauthorized actual costs should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('toll_amount', $exception->errors());
        }

        $quote = app(TaxiPricingService::class)->quote([...$input, 'authorized_actual_costs' => true]);

        $this->assertSame('12.50', $quote['breakdown']['toll']);
        $this->assertSame('5.25', $quote['breakdown']['parking']);
        $this->assertSame('117.75', $quote['breakdown']['grand_total']);
        $this->assertSame('12.50', $quote['snapshot']['inputs']['toll_amount']);
    }

    public function test_inactive_rate_card_is_ignored_for_platform_fallback(): void
    {
        $inactive = $this->rateCard(TripType::OneWay);
        $inactive->update(['is_active' => false]);
        $vehicleType = VehicleType::factory()->create();
        $fallback = $this->rateCard(TripType::OneWay, null, $vehicleType);
        $this->rule($fallback, TaxiRateRuleCode::BaseFare, '80');

        $quote = app(TaxiPricingService::class)->quote([
            'trip_type' => TripType::OneWay->value,
            'currency' => 'USD',
            'vehicle_type_id' => $vehicleType->id,
        ]);

        $this->assertSame($fallback->id, $quote['rate_card']->id);
        $this->assertSame('80.00', $quote['breakdown']['grand_total']);
    }

    public function test_booking_ignores_client_totals_and_keeps_immutable_pricing_snapshot(): void
    {
        Setting::setValue('taxi.default_currency', 'USD');
        $card = $this->rateCard(TripType::OneWay);
        $this->rule($card, TaxiRateRuleCode::BaseFare, '25');
        $this->rule($card, TaxiRateRuleCode::DistanceRate, '5', TaxiRateCalculationType::PerKilometer, '2');
        $booking = app(TaxiBookingService::class)->create([
            'trip_type' => TripType::OneWay->value,
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'pickup_address' => 'Station',
            'drop_address' => 'Hotel',
            'passenger_count' => 2,
            'customer_name' => 'Pricing Customer',
            'customer_phone' => '555100200',
            'quoted_distance_km' => '10',
            'base_amount' => '0.01',
            'extra_amount' => '0.01',
            'discount_amount' => '99999',
            'tax_amount' => '0.01',
            'total_amount' => '0.01',
        ]);

        $this->assertSame('65.00', $booking->base_amount);
        $this->assertSame('0.00', $booking->extra_amount);
        $this->assertSame('0.00', $booking->discount_amount);
        $this->assertSame('65.00', $booking->total_amount);
        $this->assertSame($card->id, $booking->taxi_rate_card_id);
        $this->assertSame('65.00', $booking->pricing_snapshot['grand_total']);

        $card->rules()->where('code', TaxiRateRuleCode::BaseFare->value)->update(['amount' => '500']);

        $historical = $booking->fresh();
        $this->assertSame('65.00', $historical->total_amount);
        $this->assertSame('25.00', $historical->pricing_snapshot['breakdown']['base_fare']);
    }

    public function test_admin_can_create_platform_rate_card_with_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->actingAs($admin)->post(route('admin.taxi.pricing.store', absolute: false), $this->rateCardPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $card = TaxiRateCard::where('name', 'Commercial one-way')->firstOrFail();
        $response->assertRedirect(route('admin.taxi.pricing.edit', $card, absolute: false));
        $this->assertNull($card->vendor_profile_id);
        $this->assertSame('USD', $card->currency);
        $this->assertSame(2, $card->rules()->count());
    }

    public function test_vendor_rate_card_scope_is_forced_and_cross_vendor_access_returns_404(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = VendorProfile::factory()->create(['user_id' => $vendorUser->id, 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => 'vendor']);
        $otherVendor = VendorProfile::factory()->create(['user_id' => $otherUser->id, 'is_active' => true]);
        $payload = [...$this->rateCardPayload(), 'vendor_profile_id' => $otherVendor->id];

        $response = $this->actingAs($vendorUser)->post(route('vendor.taxi.pricing.store', absolute: false), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $card = TaxiRateCard::where('name', 'Commercial one-way')->firstOrFail();
        $response->assertRedirect(route('vendor.taxi.pricing.edit', $card, absolute: false));
        $this->assertSame($vendor->id, $card->vendor_profile_id);

        $this->actingAs($otherUser)
            ->get(route('vendor.taxi.pricing.edit', $card, absolute: false))
            ->assertNotFound();
        $this->actingAs($otherUser)
            ->put(route('vendor.taxi.pricing.update', $card, absolute: false), $this->rateCardPayload())
            ->assertNotFound();
    }

    private function rateCard(
        TripType $tripType,
        ?VendorProfile $vendor = null,
        ?VehicleType $vehicleType = null,
    ): TaxiRateCard {
        return TaxiRateCard::create([
            'vendor_profile_id' => $vendor?->id,
            'vehicle_type_id' => $vehicleType?->id,
            'name' => fake()->unique()->words(3, true),
            'trip_type' => $tripType->value,
            'currency' => 'USD',
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed>|null $configuration */
    private function rule(
        TaxiRateCard $card,
        TaxiRateRuleCode $code,
        string $amount,
        TaxiRateCalculationType $calculationType = TaxiRateCalculationType::Fixed,
        ?string $includedQuantity = null,
        ?array $configuration = null,
    ): void {
        $card->rules()->create([
            'code' => $code->value,
            'calculation_type' => $calculationType->value,
            'amount' => $amount,
            'included_quantity' => $includedQuantity,
            'configuration' => $configuration,
        ]);
    }

    /** @return array<string, mixed> */
    private function rateCardPayload(): array
    {
        return [
            'vendor_profile_id' => null,
            'vehicle_type_id' => null,
            'name' => 'Commercial one-way',
            'trip_type' => TripType::OneWay->value,
            'currency' => 'usd',
            'is_active' => true,
            'effective_from' => null,
            'effective_until' => null,
            'rules' => [
                ['code' => 'base_fare', 'calculation_type' => 'fixed', 'amount' => '25'],
                ['code' => 'distance_rate', 'calculation_type' => 'per_km', 'amount' => '5', 'included_quantity' => '2'],
            ],
            'packages' => [],
        ];
    }

    public function test_confirmed_booking_persists_server_owned_confirmed_at(): void
    {
        Setting::setValue('taxi.default_currency', 'USD');
        $card = $this->rateCard(TripType::OneWay);
        $this->rule($card, TaxiRateRuleCode::BaseFare, '25');

        $booking = app(TaxiBookingService::class)->create([
            'trip_type' => TripType::OneWay->value,
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'pickup_address' => 'Station',
            'drop_address' => 'Hotel',
            'passenger_count' => 2,
            'customer_name' => 'Confirmed At Customer',
            'customer_phone' => '555100201',
            'confirmed_at' => now()->subYear()->toDateTimeString(),
        ]);

        $this->assertSame(TaxiBookingStatus::Confirmed->value, $booking->status);
        $fresh = $booking->fresh();
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertTrue($fresh->confirmed_at->greaterThan(now()->subHour()));

        // Mass assignment guard: plain create() silently drops confirmed_at,
        // so client data can never set it directly (factories bypass guards
        // by design and are not a valid check for this).
        $evil = TaxiBooking::create([
            'reference' => 'TX-GUARD-'.fake()->unique()->numerify('######'),
            'pickup_at' => now()->addDay(),
            'pickup_address' => 'Station',
            'drop_address' => 'Hotel',
            'customer_name' => 'Guard Customer',
            'customer_phone' => '555100202',
            'confirmed_at' => now()->subYear(),
        ]);
        $this->assertNull($evil->fresh()->confirmed_at);

        // Request layer never forwards confirmed_at.
        $this->assertArrayNotHasKey('confirmed_at', (new StoreTaxiBookingRequest)->rules());
    }
}
