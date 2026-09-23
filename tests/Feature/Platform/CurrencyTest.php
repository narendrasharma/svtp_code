<?php

namespace Tests\Feature\Platform;

use App\Contracts\ExchangeRateProvider;
use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\HotelBooking;
use App\Models\HotelBookingRefund;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\CurrencyConversionService;
use App\Services\HotelBookingService;
use App\Services\HotelPricingService;
use App\Services\MoneyPresenter;
use App\Services\TourBookingPricingService;
use App\Support\CurrencyRegistry;
use App\Support\HotelSettings;
use App\Support\LocalizedFormat;
use App\Support\TaxiSettings;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 13B shared multi-currency & FX foundation.
 *
 * Focused architecture-critical paths only (no full-suite runs).
 * Taxi backend and Hotel domain stay frozen: tests assert display
 * integration never mutates authoritative pricing.
 */
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        $this->seed(CurrencySeeder::class);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function setRate(string $quote, string $rate, string $source = 'manual'): void
    {
        ExchangeRate::updateOrCreate(
            ['base_currency_code' => CurrencyConversionService::fxBase(), 'quote_currency_code' => $quote],
            ['rate' => $rate, 'source' => $source, 'fetched_at' => now()]
        );
        CurrencyRegistry::forgetCache();
    }

    // ---- Currency registry ----

    public function test_admin_currency_page_permission_protected(): void
    {
        $this->get(route('admin.currencies.index'))->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('admin.currencies.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.currencies.index'))->assertOk();
    }

    public function test_currency_code_unique(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.currencies.store'), [
            'code' => 'USD', 'name' => 'Dup', 'symbol' => '$',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_code_normalized_uppercase(): void
    {
        // Registry normalizes; service layer never sees lowercase codes.
        $this->assertSame('USD', CurrencyRegistry::normalizeCode('usd'));
        $this->assertSame('', CurrencyRegistry::normalizeCode('Dollar'));
        $this->assertSame('', CurrencyRegistry::normalizeCode('₹'));
    }

    public function test_invalid_code_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.currencies.store'), [
            'code' => 'USDD', 'name' => 'Bad', 'symbol' => '$',
        ]);

        $response->assertSessionHasErrors(['code']);

        $response = $this->actingAs($this->admin())->post(route('admin.currencies.store'), [
            'code' => 'Rs', 'name' => 'Bad', 'symbol' => 'Rs',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_one_default_display_currency(): void
    {
        $this->assertSame('USD', CurrencyRegistry::defaultDisplayCode());
        $this->assertEquals(1, Currency::query()->where('is_default_display', true)->count());
    }

    public function test_default_must_remain_active(): void
    {
        $default = Currency::query()->where('is_default_display', true)->firstOrFail();
        $this->assertTrue($default->is_active);

        // Deactivating the default without replacement is refused.
        $this->actingAs($this->admin())->patch(route('admin.currencies.toggle', $default))
            ->assertSessionHasErrors(['is_active']);
        $this->assertTrue($default->fresh()->is_active);
    }

    public function test_inactive_currency_not_publicly_selectable(): void
    {
        $this->assertNotContains('AED', CurrencyRegistry::activeCodes());

        $this->post(route('currency.store'), ['currency' => 'AED'])->assertRedirect();
        $this->assertSame('USD', session(CurrencyRegistry::SESSION_KEY));
    }

    public function test_historical_records_using_inactive_currency_remain_readable(): void
    {
        $booking = HotelBooking::factory()->create(['currency' => 'USD', 'total' => '220.00']);

        Currency::query()->where('code', 'USD')->update(['is_active' => false]);
        Currency::query()->where('code', 'INR')->update(['is_default_display' => true]);
        CurrencyRegistry::forgetCache();

        // Deactivation hides from selector but never corrupts history.
        $this->assertNotContains('USD', CurrencyRegistry::activeCodes());
        $fresh = $booking->fresh();
        $this->assertSame('USD', $fresh->currency);
        $this->assertSame('220.00', number_format((float) $fresh->total, 2, '.', ''));
    }

    public function test_order_respected(): void
    {
        $codes = array_column(CurrencyRegistry::activeCurrencies(), 'code');
        $this->assertSame(['USD', 'INR', 'EUR', 'GBP'], $codes);
    }

    // ---- Selection ----

    public function test_visitor_can_select_active_currency(): void
    {
        $response = $this->post(route('currency.store'), ['currency' => 'inr']);
        $response->assertRedirect();
        $this->assertSame('INR', session(CurrencyRegistry::SESSION_KEY));
        $response->assertCookie(CurrencyRegistry::COOKIE_KEY, 'INR');
    }

    public function test_selection_persists(): void
    {
        $this->post(route('currency.store'), ['currency' => 'EUR']);

        $props = $this->get('/')->viewData('page')['props'];
        $this->assertSame('EUR', $props['currency']['selected']);
    }

    public function test_inactive_selection_rejected_falls_back(): void
    {
        $this->post(route('currency.store'), ['currency' => 'AED']);
        $this->assertSame('USD', CurrencyRegistry::resolveSelected());
    }

    public function test_explicit_selection_beats_default(): void
    {
        $props = $this->withSession([CurrencyRegistry::SESSION_KEY => 'GBP'])->get('/')->viewData('page')['props'];
        $this->assertSame('GBP', $props['currency']['selected']);
        $this->assertSame('USD', $props['currency']['default']);
    }

    public function test_currency_shared_through_inertia(): void
    {
        $props = $this->get('/')->viewData('page')['props'];

        $this->assertSame('USD', $props['currency']['selected']);
        $this->assertSame('USD', $props['currency']['default']);
        $this->assertNotEmpty($props['currency']['currencies']);
        $this->assertSame(['USD', 'INR', 'EUR', 'GBP'], array_column($props['currency']['currencies'], 'code'));
    }

    public function test_hotels_disabled_does_not_disable_currency_infrastructure(): void
    {
        Setting::setValue('modules.hotels.enabled', '0');

        $this->actingAs($this->admin())->get(route('admin.currencies.index'))->assertOk();
        $this->assertNotEmpty(CurrencyRegistry::activeCurrencies());
        $this->assertSame('USD', CurrencyRegistry::defaultDisplayCode());
    }

    // ---- Conversion ----

    public function test_same_currency_returns_rate_one(): void
    {
        $result = CurrencyConversionService::convert('10000.00', 'INR', 'INR');

        $this->assertTrue($result['available']);
        $this->assertFalse($result['conversion_applied']);
        $this->assertSame('1', $result['exchange_rate']);
        $this->assertSame('10000.00', $result['converted_amount']);
    }

    public function test_direct_conversion_correct(): void
    {
        $this->setRate('INR', '83.5000000000');

        $result = CurrencyConversionService::convert('100.00', 'USD', 'INR');

        $this->assertTrue($result['available']);
        $this->assertTrue($result['conversion_applied']);
        $this->assertSame('8350.00', $result['converted_amount']);
    }

    public function test_inverse_conversion_correct(): void
    {
        $this->setRate('INR', '83.5000000000');

        $result = CurrencyConversionService::convert('8350.00', 'INR', 'USD');

        $this->assertTrue($result['available']);
        $this->assertSame('100.00', $result['converted_amount']);
    }

    public function test_cross_rate_conversion_correct(): void
    {
        $this->setRate('INR', '83.5000000000');
        $this->setRate('EUR', '0.9200000000');

        // EUR → INR via USD base: 92.00 EUR = 100 USD = 8350 INR.
        $result = CurrencyConversionService::convert('92.00', 'EUR', 'INR');

        $this->assertTrue($result['available']);
        $this->assertSame('8350.00', $result['converted_amount']);
    }

    public function test_missing_rate_does_not_fabricate_value(): void
    {
        $result = CurrencyConversionService::convert('100.00', 'USD', 'GBP');

        $this->assertFalse($result['available']);
        $this->assertNull($result['converted_amount']);
        $this->assertNull($result['exchange_rate']);
        $this->assertSame('100.00', $result['source_amount']);
    }

    public function test_stale_rate_behavior_follows_policy(): void
    {
        ExchangeRate::updateOrCreate(
            ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR'],
            ['rate' => '83.5000000000', 'source' => 'manual', 'fetched_at' => now()->subDays(10)]
        );
        CurrencyRegistry::forgetCache();

        // Stale display rate is still usable but flagged — bookings never block.
        $result = CurrencyConversionService::convert('100.00', 'USD', 'INR');

        $this->assertTrue($result['available']);
        $this->assertTrue($result['stale']);
        $this->assertSame('8350.00', $result['converted_amount']);
    }

    public function test_manual_rate_works(): void
    {
        $this->actingAs($this->admin())->post(route('admin.exchange-rates.store'), [
            'quote_currency_code' => 'INR', 'rate' => '84.25',
        ])->assertRedirect();

        $this->assertDatabaseHas('exchange_rates', [
            'base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'source' => 'manual',
        ]);

        $result = CurrencyConversionService::convert('100.00', 'USD', 'INR');
        $this->assertSame('8425.00', $result['converted_amount']);
    }

    public function test_decimal_safe_calculation(): void
    {
        $this->setRate('INR', '83.3333333333');

        // Binary float would give 833.3333333329999…; BCMath stays exact.
        $result = CurrencyConversionService::convert('10.00', 'USD', 'INR');

        $this->assertSame('833.33', $result['converted_amount']);
    }

    public function test_target_decimal_rounding_correct(): void
    {
        Currency::create(['code' => 'JPY', 'name' => 'Yen', 'symbol' => '¥', 'decimal_digits' => 0, 'is_active' => true, 'sort_order' => 50]);
        $this->setRate('JPY', '150.0000000000');
        CurrencyRegistry::forgetCache();

        $result = CurrencyConversionService::convert('100.00', 'USD', 'JPY');

        $this->assertSame('15000', $result['converted_amount']);
    }

    public function test_no_double_conversion(): void
    {
        $this->setRate('INR', '83.5000000000');

        $dto = MoneyPresenter::present('119.50', 'USD', 'USD');

        $this->assertFalse($dto['conversion_applied']);
        $this->assertSame($dto['amount'], $dto['display_amount']);
        $this->assertSame('USD', $dto['display_currency']);
    }

    // ---- Provider ----

    public function test_provider_interface_can_return_rates(): void
    {
        $this->setRate('INR', '83.5000000000');

        $rates = app(ExchangeRateProvider::class)->fetchRates('USD', ['INR', 'EUR']);

        $this->assertSame('83.5000000000', number_format((float) $rates['INR'], 10, '.', ''));
        $this->assertArrayNotHasKey('EUR', $rates);
    }

    public function test_failed_provider_preserves_last_known_good(): void
    {
        $this->setRate('INR', '83.5000000000');

        $failing = new class implements ExchangeRateProvider
        {
            public function key(): string
            {
                return 'remote';
            }

            public function fetchRates(string $base, array $quotes): array
            {
                throw new \RuntimeException('provider down');
            }
        };

        app()->instance(ExchangeRateProvider::class, $failing);

        $this->artisan('currency:refresh-rates')->assertFailed();

        $this->assertDatabaseHas('exchange_rates', [
            'base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83.5000000000',
        ]);
    }

    public function test_invalid_zero_negative_rate_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.exchange-rates.store'), [
            'quote_currency_code' => 'INR', 'rate' => '0',
        ])->assertSessionHasErrors(['rate']);

        $this->actingAs($this->admin())->post(route('admin.exchange-rates.store'), [
            'quote_currency_code' => 'INR', 'rate' => '-5',
        ])->assertSessionHasErrors(['rate']);

        $this->assertDatabaseMissing('exchange_rates', ['quote_currency_code' => 'INR']);
    }

    public function test_refresh_updates_fetched_at_source(): void
    {
        $this->setRate('INR', '80.0000000000');

        $this->artisan('currency:refresh-rates')->assertSuccessful();

        $row = ExchangeRate::query()->where('quote_currency_code', 'INR')->firstOrFail();
        $this->assertSame('manual', $row->source);
        $this->assertTrue($row->fetched_at->isToday());
    }

    public function test_manual_fallback_works_without_provider(): void
    {
        // No rates stored at all: display falls back to authoritative.
        $dto = MoneyPresenter::present('10000.00', 'INR', 'USD');

        $this->assertFalse($dto['conversion_applied']);
        $this->assertSame('INR', $dto['display_currency']);
        $this->assertSame($dto['formatted'], $dto['display_formatted']);
    }

    // ---- Security ----

    public function test_customer_cannot_manage_rates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->post(route('admin.currencies.store'), ['code' => 'CHF', 'name' => 'Franc', 'symbol' => 'CHF'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->post(route('admin.exchange-rates.store'), ['quote_currency_code' => 'INR', 'rate' => '80'])
            ->assertForbidden();
    }

    public function test_vendor_cannot_manage_platform_rates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'vendor']))
            ->get(route('admin.currencies.index'))->assertForbidden();
    }

    public function test_provider_secret_not_shared_through_inertia(): void
    {
        config()->set('currency.provider', 'manual');

        $props = $this->get('/')->viewData('page')['props'];
        $encoded = json_encode($props['currency']);

        $this->assertStringNotContainsString('secret', strtolower($encoded));
        $this->assertStringNotContainsString('api_key', strtolower($encoded));
        $this->assertStringNotContainsString('apikey', strtolower($encoded));
        $this->assertArrayNotHasKey('provider', $props['currency']);
    }

    public function test_forged_display_amount_cannot_alter_authoritative_quote(): void
    {
        Setting::setValue('modules.hotels.enabled', '1');
        Setting::setValue('hotel.booking.enabled', '1');
        $this->setRate('INR', '83.5000000000');

        [$property, $room, $plan] = $this->hotel();
        $quote = app(HotelPricingService::class)->quote($plan, '2027-03-10', '2027-03-12', 1, 2, 0, false);

        // Visitor views in INR, then submits a forged USD-ish total + display fields.
        $payload = [
            'room_type_id' => $room->id, 'rate_plan_id' => $plan->id,
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12',
            'rooms' => 1, 'adults' => 2, 'children' => 0,
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '+911234567890',
            'quote_fingerprint' => HotelBookingService::fingerprint($quote),
            'total' => '1.00', 'display_total' => '1.00', 'currency' => 'INR',
        ];

        $customer = User::factory()->create(['role' => 'customer']);
        $this->withSession([CurrencyRegistry::SESSION_KEY => 'INR'])
            ->actingAs($customer)
            ->post(route('hotel-booking.store', $property->slug), $payload)
            ->assertRedirect();

        $booking = HotelBooking::query()->latest('id')->firstOrFail();
        $this->assertSame('USD', $booking->currency);
        $this->assertSame($quote['total'], number_format((float) $booking->total, 2, '.', ''));
    }

    public function test_client_selected_currency_cannot_alter_stored_hotel_base_price(): void
    {
        Setting::setValue('modules.hotels.enabled', '1');
        [$property] = $this->hotel();

        $this->withSession([CurrencyRegistry::SESSION_KEY => 'INR'])
            ->get(route('hotels.rates', $property->slug, absolute: false).'?check_in=2027-03-10&check_out=2027-03-12')
            ->assertOk();

        $this->assertSame('USD', $property->fresh()->currency);
    }

    // ---- Hotel integration ----

    public function test_hotel_authoritative_quote_unchanged_by_display_selection(): void
    {
        Setting::setValue('modules.hotels.enabled', '1');
        [$property, $room, $plan] = $this->hotel();
        $this->setRate('INR', '83.5000000000');

        $pricing = app(HotelPricingService::class);
        $plain = $pricing->quote($plan, '2027-03-10', '2027-03-12', 1, 2, 0, false);

        $this->withSession([CurrencyRegistry::SESSION_KEY => 'INR']);
        $selected = $pricing->quote($plan, '2027-03-10', '2027-03-12', 1, 2, 0, false);

        $this->assertSame('USD', $plain['currency']);
        $this->assertSame($plain['total'], $selected['total']);
    }

    public function test_hotel_display_conversion_correct(): void
    {
        Setting::setValue('modules.hotels.enabled', '1');
        [$property] = $this->hotel();
        $this->setRate('INR', '83.5000000000');

        $response = $this->withSession([CurrencyRegistry::SESSION_KEY => 'INR'])
            ->getJson(route('hotels.rates', $property->slug, absolute: false).'?check_in=2027-03-10&check_out=2027-03-12');

        $response->assertOk();
        $plan = $response->json('room_types.0.plans.0');

        $this->assertSame('USD', $plan['currency']);
        $this->assertSame('INR', $plan['display_total']['display_currency']);
        $this->assertTrue($plan['display_total']['conversion_applied']);
        $this->assertSame(
            CurrencyConversionService::convert($plan['total'], 'USD', 'INR')['converted_amount'],
            $plan['display_total']['display_amount']
        );
    }

    public function test_hotel_booking_persists_authoritative_currency(): void
    {
        $booking = HotelBooking::factory()->create(['currency' => 'USD', 'total' => '220.00']);

        $this->setRate('INR', '90.0000000000');

        $fresh = $booking->fresh();
        $this->assertSame('USD', $fresh->currency);
        $this->assertSame('220.00', number_format((float) $fresh->total, 2, '.', ''));
        $this->assertSame('220.00', number_format((float) ($fresh->pricing_snapshot['total'] ?? 0), 2, '.', ''));
    }

    public function test_visitor_display_fx_is_not_treated_as_booking_total(): void
    {
        $booking = HotelBooking::factory()->create(['currency' => 'USD', 'total' => '220.00']);
        $this->setRate('INR', '83.5000000000');

        $dto = MoneyPresenter::present($booking->total, $booking->currency, 'INR');

        // Display conversion exists, but the booking row is untouched.
        $this->assertTrue($dto['conversion_applied']);
        $this->assertSame('USD', $booking->fresh()->currency);
        $this->assertSame('220.00', number_format((float) $booking->fresh()->total, 2, '.', ''));
        $this->assertArrayNotHasKey('fx', $booking->fresh()->pricing_snapshot ?? []);
    }

    public function test_hotel_refund_amount_remains_authoritative_currency(): void
    {
        $booking = HotelBooking::factory()->create(['currency' => 'USD', 'total' => '220.00']);
        $refund = HotelBookingRefund::create([
            'hotel_booking_id' => $booking->id, 'refund_number' => 'HR-1',
            'idempotency_key' => 'k1', 'amount' => '220.00', 'currency' => $booking->currency,
            'status' => 'pending', 'reason' => 'hotel_cancellation', 'requested_at' => now(),
        ]);

        $this->setRate('INR', '50.0000000000');

        $this->assertSame('USD', $refund->fresh()->currency);
        $this->assertSame('220.00', number_format((float) $refund->fresh()->amount, 2, '.', ''));
    }

    public function test_reschedule_amount_difference_remains_authoritative_currency(): void
    {
        [$property, $room, $plan] = $this->hotel();

        $pricing = app(HotelPricingService::class);
        $old = $pricing->quote($plan, '2027-03-10', '2027-03-12', 1, 2, 0, false);
        $new = $pricing->quote($plan, '2027-03-17', '2027-03-19', 1, 2, 0, false);

        // Difference accounting compares authoritative-with-authoritative.
        $difference = bcsub($new['total'], $old['total'], 2);

        $this->assertSame('USD', $new['currency']);
        $this->assertSame('USD', $old['currency']);
        $this->assertTrue(is_numeric($difference));
    }

    // ---- Tour / Taxi regression ----

    public function test_representative_tour_price_remains_source_authoritative(): void
    {
        $package = TourPackage::factory()->create();

        $this->withSession([CurrencyRegistry::SESSION_KEY => 'EUR'])
            ->get(route('packages.show', $package->slug, absolute: false))
            ->assertOk();

        $this->assertSame(TourBookingPricingService::DEFAULT_CURRENCY, 'INR');

        $quote = app(TourBookingPricingService::class)->quote($package, 2, 0);
        $this->assertSame('INR', $quote['currency']);
    }

    public function test_currency_selection_does_not_mutate_tour_record(): void
    {
        $package = TourPackage::factory()->create();
        $before = $package->only(['price', 'discount_price']);

        $this->post(route('currency.store'), ['currency' => 'GBP']);

        $this->assertSame($before, $package->fresh()->only(['price', 'discount_price']));
    }

    public function test_taxi_calculation_unaffected(): void
    {
        // Taxi domain default stays INR; registry changes never rewrite it.
        $this->assertSame('INR', TaxiSettings::get('taxi.default_currency'));
        $this->assertSame('INR', TourBookingPricingService::DEFAULT_CURRENCY);
        $this->assertSame('USD', HotelSettings::defaultCurrency());
    }

    public function test_shared_currency_infrastructure_does_not_depend_on_hotels_module(): void
    {
        Setting::setValue('modules.hotels.enabled', '0');

        $result = CurrencyConversionService::convert('100.00', 'USD', 'USD');
        $this->assertTrue($result['available']);

        $props = $this->get('/')->viewData('page')['props'];
        $this->assertNotEmpty($props['currency']['currencies']);
    }

    // ---- Formatting ----

    public function test_inr_formatted_using_locale_aware_formatter(): void
    {
        $formatted = LocalizedFormat::money('10000.00', 'INR', 'en');

        $this->assertIsString($formatted);
        $this->assertStringContainsString('10,000.00', $formatted);
        $this->assertStringContainsString('₹', $formatted);
    }

    public function test_usd_formatting(): void
    {
        $formatted = LocalizedFormat::money('119.50', 'USD', 'en');

        $this->assertIsString($formatted);
        $this->assertStringContainsString('119.50', $formatted);
    }

    public function test_rtl_locale_formatting_remains_valid(): void
    {
        $formatted = LocalizedFormat::money('119.50', 'USD', 'ar');

        $this->assertIsString($formatted);
        $this->assertStringContainsString('119', $formatted);
    }

    public function test_zero_amount_formats_correctly(): void
    {
        $this->assertNotNull(LocalizedFormat::money('0.00', 'USD', 'en'));
        $this->assertStringContainsString('0.00', (string) LocalizedFormat::money('0.00', 'USD', 'en'));
    }

    public function test_large_amount_formatting_safe(): void
    {
        $formatted = LocalizedFormat::money('999999999.99', 'USD', 'en');

        $this->assertIsString($formatted);
        $this->assertStringContainsString('999,999,999.99', $formatted);
    }

    // ---- Historical immutability ----

    public function test_changing_exchange_rate_does_not_change_existing_booking_authoritative_total(): void
    {
        $booking = HotelBooking::factory()->create(['currency' => 'USD', 'total' => '220.00']);

        $this->setRate('INR', '83.5000000000');
        $this->setRate('INR', '95.0000000000');

        $this->assertSame('220.00', number_format((float) $booking->fresh()->total, 2, '.', ''));
        $this->assertSame('USD', $booking->fresh()->currency);
    }

    // ---- helpers ----

    /**
     * @return array{0: Property, 1: HotelRoomType, 2: HotelRatePlan}
     */
    protected function hotel(): array
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
            'currency' => 'USD',
        ]);
        $room = HotelRoomType::factory()->create([
            'property_id' => $property->id,
            'status' => RoomTypeStatus::Active->value,
            'total_units' => 10,
        ]);
        $plan = HotelRatePlan::factory()->create([
            'property_id' => $property->id,
            'hotel_room_type_id' => $room->id,
            'currency' => 'USD',
            'base_rate' => '100.00',
        ]);

        return [$property->fresh(), $room->fresh(), $plan->fresh()];
    }
}
