<?php

namespace Tests\Feature\Hotel;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\HotelChargeRule;
use App\Models\HotelCustomFieldDefinition;
use App\Models\HotelDailyRate;
use App\Models\HotelRatePlan;
use App\Models\HotelRateSeason;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelAvailabilityService;
use App\Services\HotelCustomFieldService;
use App\Services\HotelPricingService;
use App\Support\AdminNavigation;
use App\Support\ModuleManager;
use Carbon\Carbon;
use Database\Seeders\HotelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HotelPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        app(ModuleManager::class)->setEnabled('hotels', true);
    }

    // ---- helpers ----------------------------------------------------

    protected function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin->fresh();
    }

    protected function makeVendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        return $vendor->fresh();
    }

    protected function propertyFor(?VendorProfile $vendor = null, array $overrides = []): Property
    {
        return Property::factory()->create(array_merge([
            'vendor_profile_id' => $vendor?->id,
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
            'currency' => 'USD',
        ], $overrides))->fresh();
    }

    protected function roomFor(Property $property, array $overrides = []): HotelRoomType
    {
        return HotelRoomType::factory()->create(array_merge([
            'property_id' => $property->id,
            'status' => RoomTypeStatus::Active->value,
            'inventory_mode' => HotelRoomType::INVENTORY_AGGREGATE,
            'total_units' => 10,
            'max_adults' => 2,
            'max_children' => 1,
            'max_occupancy' => 3,
        ], $overrides))->fresh();
    }

    protected function planFor(HotelRoomType $room, array $overrides = []): HotelRatePlan
    {
        return HotelRatePlan::factory()->create(array_merge([
            'property_id' => $room->property_id,
            'hotel_room_type_id' => $room->id,
            'currency' => 'USD',
            'base_rate' => '100.00',
        ], $overrides));
    }

    protected function planPayload(HotelRoomType $room, array $overrides = []): array
    {
        return array_merge([
            'property_id' => $room->property_id,
            'hotel_room_type_id' => $room->id,
            'name' => 'Flexible Room Only',
            'code' => 'flex-room-only',
            'meal_plan' => HotelRatePlan::MEAL_ROOM_ONLY,
            'cancellation_mode' => HotelRatePlan::CANCEL_FLEXIBLE,
            'base_adults' => 2,
            'base_rate' => '100.00',
        ], $overrides);
    }

    protected function pricing(): HotelPricingService
    {
        return app(HotelPricingService::class);
    }

    protected function ratesUrl(Property $property, array $query): string
    {
        return route('hotels.rates', $property->slug, absolute: false).'?'.http_build_query($query);
    }

    protected function stay(): array
    {
        return ['check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'rooms' => 1];
    }

    // ---- 1 module disabled blocks admin -------------------------------

    public function test_module_disabled_blocks_admin_pricing_routes(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.rate-plans.index', absolute: false)
        )->assertNotFound();
    }

    // ---- 2 module disabled blocks vendor --------------------------------

    public function test_module_disabled_blocks_vendor_pricing_routes(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeVendor())->get(
            route('vendor.hotel.rate-plans.index', absolute: false)
        )->assertNotFound();
    }

    // ---- 3 admin can access rate plans ----------------------------------

    public function test_admin_can_access_rate_plans(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.rate-plans.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
            ])
        )->assertOk();
    }

    // ---- 4 vendor own access --------------------------------------------

    public function test_vendor_can_access_own_rate_plans(): void
    {
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);
        $room = $this->roomFor($property);

        $this->actingAs($vendor)->get(
            route('vendor.hotel.rate-plans.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
            ])
        )->assertOk();
    }

    // ---- 5 vendor foreign blocked ---------------------------------------

    public function test_vendor_cannot_access_foreign_rate_plan(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $foreignPlan = $this->planFor($this->roomFor($this->propertyFor($vendorA->vendorProfile)));

        $this->actingAs($vendorB)->put(
            route('vendor.hotel.rate-plans.update', $foreignPlan, absolute: false),
            ['hotel_room_type_id' => $foreignPlan->hotel_room_type_id, 'name' => 'Hijacked', 'code' => $foreignPlan->code, 'meal_plan' => 'room_only', 'cancellation_mode' => 'flexible', 'base_adults' => 2, 'base_rate' => '1.00']
        )->assertNotFound();

        $this->assertNotSame('Hijacked', $foreignPlan->fresh()->name);
    }

    // ---- 6 vendor foreign room blocked ----------------------------------

    public function test_vendor_cannot_create_plan_for_foreign_room_type(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $foreignRoom = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.rate-plans.store', absolute: false),
            $this->planPayload($foreignRoom, ['property_id' => null])
        )->assertNotFound();

        $this->assertSame(0, HotelRatePlan::count());
    }

    // ---- 7 base rate stored ---------------------------------------------

    public function test_base_rate_stored_safely(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.rate-plans.store', absolute: false),
            $this->planPayload($room)
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_rate_plans', [
            'hotel_room_type_id' => $room->id, 'code' => 'flex-room-only', 'base_rate' => '100.00',
        ]);
    }

    // ---- 8 negative base rejected ---------------------------------------

    public function test_negative_base_rate_rejected(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.rate-plans.store', absolute: false),
            $this->planPayload($room, ['base_rate' => '-5.00'])
        )->assertSessionHasErrors('base_rate');

        $this->assertSame(0, HotelRatePlan::count());
    }

    // ---- 9 ISO currency -------------------------------------------------

    public function test_iso_currency_validation(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.charges.store', absolute: false),
            ['property_id' => $property->id, 'name' => 'Bad Tax', 'charge_type' => 'tax', 'calculation' => 'percentage', 'value' => '10.00', 'currency' => 'EURO']
        )->assertSessionHasErrors('currency');

        $this->assertSame(0, HotelChargeRule::count());
    }

    // ---- 10 currency policy ---------------------------------------------

    public function test_rate_plan_currency_matches_property_policy(): void
    {
        $room = $this->roomFor($this->propertyFor(null, ['currency' => 'USD']));

        // Browser-submitted foreign currency is overridden server-side.
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.rate-plans.store', absolute: false),
            $this->planPayload($room, ['code' => 'forced-eur'])
        )->assertRedirect();

        $this->assertSame('USD', HotelRatePlan::where('code', 'forced-eur')->firstOrFail()->currency);

        // Tampered stored currency fails the quote outright.
        $plan = $this->planFor($room);
        $plan->forceFill(['currency' => 'EUR'])->save();

        try {
            $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');
            $this->fail('Foreign-currency plan quoted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('currency', $e->errors());
        }
    }

    // ---- 11 inactive not public -----------------------------------------

    public function test_inactive_rate_plan_not_public(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $this->planFor($room, ['code' => 'old-plan', 'is_active' => false]);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $codes = collect($response->json('room_types'))->pluck('plans.*.code')->flatten()->all();
        $this->assertNotContains('old-plan', $codes);
    }

    // ---- 12 active public ------------------------------------------------

    public function test_active_rate_plan_public(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $this->planFor($room, ['code' => 'flex-room-only']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $codes = collect($response->json('room_types'))->pluck('plans.*.code')->flatten()->all();
        $this->assertContains('flex-room-only', $codes);
    }

    // ---- 13 relationship validated ---------------------------------------

    public function test_room_type_property_relationship_validated(): void
    {
        $property = $this->propertyFor();
        $roomA = $this->roomFor($property);
        $roomB = $this->roomFor($property);
        $this->planFor($roomA, ['code' => 'plan-a']);
        $this->planFor($roomB, ['code' => 'plan-b']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay() + ['room_type_id' => $roomA->id]))->assertOk();

        $this->assertCount(1, $response->json('room_types'));
        $codes = collect($response->json('room_types'))->pluck('plans.*.code')->flatten()->all();
        $this->assertContains('plan-a', $codes);
        $this->assertNotContains('plan-b', $codes);
    }

    // ---- 14 base fallback --------------------------------------------------

    public function test_base_price_fallback_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertTrue($quote['available']);
        $this->assertSame(['100.00', '100.00'], array_column($quote['nightly'], 'base_rate'));
        $this->assertSame('200.00', $quote['subtotal']);
        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 15 daily beats base ---------------------------------------------------

    public function test_daily_override_beats_base_price(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $this->pricing()->setDay($plan, '2027-03-11', ['amount_override' => '150.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame(['100.00', '150.00'], array_column($quote['nightly'], 'base_rate'));
        $this->assertSame('250.00', $quote['subtotal']);
    }

    // ---- 16 season beats base ---------------------------------------------------------

    public function test_seasonal_rule_beats_base_price(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '20.00', 'priority' => 1,
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame(['120.00', '120.00'], array_column($quote['nightly'], 'base_rate'));
        $this->assertSame('240.00', $quote['subtotal']);
    }

    // ---- 17 daily beats season -------------------------------------------------------------------

    public function test_daily_override_beats_seasonal_rule(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '20.00', 'priority' => 1,
        ]);
        $this->pricing()->setDay($plan, '2027-03-11', ['amount_override' => '250.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame(['120.00', '250.00'], array_column($quote['nightly'], 'base_rate'));
    }

    // ---- 18 higher priority wins ---------------------------------------------------------------------------------

    public function test_higher_priority_season_wins(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id, 'name' => 'Low',
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '10.00', 'priority' => 5,
        ]);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id, 'name' => 'High',
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '20.00', 'priority' => 10,
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame('120.00', $quote['nightly'][0]['base_rate']);
    }

    // ---- 19 deterministic overlap ------------------------------------------------------------------------------------------------

    public function test_overlapping_season_resolution_deterministic(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $first = HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id, 'name' => 'First',
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'fixed_amount', 'adjustment_value' => '5.00', 'priority' => 5,
        ]);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id, 'name' => 'Second',
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'fixed_amount', 'adjustment_value' => '50.00', 'priority' => 5,
        ]);

        $firstQuote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');
        $secondQuote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        // Same priority → lowest id wins, stable across repeated reads.
        $this->assertSame('105.00', $firstQuote['nightly'][0]['base_rate']);
        $this->assertSame($firstQuote['nightly'][0]['base_rate'], $secondQuote['nightly'][0]['base_rate']);
        $this->assertSame($first->id, HotelRateSeason::where('hotel_rate_plan_id', $plan->id)->orderBy('id')->first()->id);
    }

    // ---- 20 weekday applies ----------------------------------------------------------------------------------------------------------------------

    public function test_weekday_rule_applies_only_configured_weekdays(): void
    {
        // 2027-03-12 is a Friday, 2027-03-14 a Sunday.
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '20.00', 'priority' => 1,
            'applicable_weekdays' => [5, 6],
        ]);

        $friday = $this->pricing()->quote($plan, '2027-03-12', '2027-03-13');
        $sunday = $this->pricing()->quote($plan, '2027-03-14', '2027-03-15');

        $this->assertSame('120.00', $friday['nightly'][0]['base_rate']);
        $this->assertSame('100.00', $sunday['nightly'][0]['base_rate']);
    }

    // ---- 21 non-applicable falls back ------------------------------------------------------------------------------------------------------------------------------------

    public function test_non_applicable_weekday_falls_back(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '20.00', 'priority' => 1,
            'applicable_weekdays' => [5, 6],
        ]);

        // Wednesday night + Thursday night: no rule applies.
        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame(['100.00', '100.00'], array_column($quote['nightly'], 'base_rate'));
    }

    // ---- 22 percentage increase ---------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_percentage_increase_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '200.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '15.00', 'priority' => 1,
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame('230.00', $quote['nightly'][0]['base_rate']);
    }

    // ---- 23 percentage discount -------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_percentage_discount_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '200.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '-10.00', 'priority' => 1,
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame('180.00', $quote['nightly'][0]['base_rate']);
    }

    // ---- 24 fixed adjustment ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_fixed_adjustment_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'fixed_amount', 'adjustment_value' => '15.00', 'priority' => 1,
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame('115.00', $quote['nightly'][0]['base_rate']);
    }

    // ---- 25 fixed price -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_fixed_price_season_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'fixed_price', 'adjustment_value' => '80.00', 'priority' => 1,
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame('80.00', $quote['nightly'][0]['base_rate']);
    }

    // ---- 26 no negative ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_discount_cannot_make_price_negative(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '-100.00', 'priority' => 1,
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame('0.00', $quote['nightly'][0]['base_rate']);
        $this->assertSame('0.00', $quote['total']);
    }

    // ---- 27 sparse -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_sparse_dates_need_no_daily_row(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-13');

        $this->assertTrue($quote['available']);
        $this->assertSame('300.00', $quote['subtotal']);
        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 28 clear restores -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_clear_override_restores_inherited_price(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $this->pricing()->setDay($plan, '2027-03-10', ['amount_override' => '180.00']);
        $this->pricing()->clearDay($plan, '2027-03-10');

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame('100.00', $quote['nightly'][0]['base_rate']);
        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 29 default row removed -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_default_equivalent_daily_row_removed(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));
        $this->pricing()->setDay($plan, '2027-03-10', ['amount_override' => '180.00']);
        $this->assertSame(1, HotelDailyRate::count());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.store', absolute: false),
            ['hotel_rate_plan_id' => $plan->id, 'rate_date' => '2027-03-10']
        )->assertRedirect();

        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 30 exclusive checkout pricing -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_check_in_inclusive_check_out_exclusive_pricing(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $this->pricing()->setDay($plan, '2027-03-12', ['amount_override' => '999.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame(['2027-03-10', '2027-03-11'], array_column($quote['nightly'], 'date'));
        $this->assertSame('200.00', $quote['subtotal']);
    }

    // ---- 31 one night -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_one_night_quote_prices_one_night(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertSame(1, $quote['nights_count']);
        $this->assertCount(1, $quote['nightly']);
        $this->assertSame('100.00', $quote['total']);
    }

    // ---- 32 multi night -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_multi_night_quote_sums_nights(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $this->pricing()->setDay($plan, '2027-03-11', ['amount_override' => '150.00']);
        $this->pricing()->setDay($plan, '2027-03-12', ['amount_override' => '100.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-13');

        $this->assertSame(3, $quote['nights_count']);
        $this->assertSame('350.00', $quote['subtotal']);
        $this->assertSame('350.00', $quote['total']);
    }

    // ---- 33 base occupancy ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_base_occupancy_respected(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), [
            'base_rate' => '100.00', 'base_adults' => 2, 'base_children' => 0,
            'extra_adult_rate' => '30.00', 'extra_child_rate' => '15.00',
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 1, 2, 0);

        $this->assertSame('0.00', $quote['nightly'][0]['extra_adult']);
        $this->assertSame('0.00', $quote['nightly'][0]['extra_child']);
        $this->assertSame('100.00', $quote['nightly'][0]['night_total']);
    }

    // ---- 34 extra adult -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_extra_adult_charge_works(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['max_adults' => 3, 'max_occupancy' => 4]);
        $plan = $this->planFor($room, ['base_rate' => '100.00', 'extra_adult_rate' => '30.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 1, 3, 0);

        $this->assertSame('30.00', $quote['nightly'][0]['extra_adult']);
        $this->assertSame('130.00', $quote['nightly'][0]['night_total']);
    }

    // ---- 35 extra child -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_extra_child_charge_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), [
            'base_rate' => '100.00', 'extra_child_rate' => '15.00',
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 1, 2, 1);

        $this->assertSame('15.00', $quote['nightly'][0]['extra_child']);
        $this->assertSame('115.00', $quote['nightly'][0]['night_total']);
    }

    // ---- 36 over occupancy rejected ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_occupancy_above_room_max_rejected(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));

        $this->expectException(ValidationException::class);
        $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 1, 5, 0);
    }

    // ---- 37 max adults -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_max_adults_enforced(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor(), ['max_adults' => 2, 'max_occupancy' => 5]));

        $this->expectException(ValidationException::class);
        $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 1, 4, 0);
    }

    // ---- 38 max children -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_max_children_enforced(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor(), ['max_children' => 1, 'max_occupancy' => 5]));

        $this->expectException(ValidationException::class);
        $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 1, 1, 3);
    }

    // ---- 39 min stay -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_minimum_stay_enforced(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['minimum_stay' => 3]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertFalse($quote['available']);
        $this->assertStringContainsString('minimum stay of 3', (string) $quote['unavailable_reason']);
    }

    // ---- 40 max stay -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_maximum_stay_enforced(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['maximum_stay' => 2]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-13');

        $this->assertFalse($quote['available']);
        $this->assertStringContainsString('maximum stay of 2', (string) $quote['unavailable_reason']);
    }

    // ---- 41 daily min override -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_daily_min_stay_override_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));
        $this->pricing()->setDay($plan, '2027-03-10', ['minimum_stay_override' => 3]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertFalse($quote['available']);
        $this->assertSame(3, $quote['restrictions']['min_stay']);
    }

    // ---- 42 CTA blocks arrival ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_closed_to_arrival_blocks_arrival(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));
        $this->pricing()->setDay($plan, '2027-03-10', ['closed_to_arrival' => true]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertFalse($quote['available']);
        $this->assertStringContainsString('Arrival', (string) $quote['unavailable_reason']);
    }

    // ---- 43 CTA stay-through -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_closed_to_arrival_does_not_block_stay_through_night(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));
        $this->pricing()->setDay($plan, '2027-03-11', ['closed_to_arrival' => true]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertTrue($quote['available']);
    }

    // ---- 44 CTD blocks departure -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_closed_to_departure_blocks_departure(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));
        $this->pricing()->setDay($plan, '2027-03-12', ['closed_to_departure' => true]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertFalse($quote['available']);
        $this->assertStringContainsString('Departure', (string) $quote['unavailable_reason']);
    }

    // ---- 45 plan stop-sell ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_rate_plan_stop_sell_blocks_plan(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));
        $this->pricing()->setDay($plan, '2027-03-11', ['stop_sell' => true]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertFalse($quote['available']);
        $this->assertStringContainsString('closed', (string) $quote['unavailable_reason']);
    }

    // ---- 46 inventory stop-sell ---------------------------------------------------------------------------------------------

    public function test_inventory_stop_sell_makes_quote_unavailable(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $plan = $this->planFor($room);
        app(HotelAvailabilityService::class)->setDay($room, '2027-03-10', ['stop_sell' => true]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertFalse($quote['available']);
        $this->assertSame(0, $quote['min_available_rooms']);
        // Price math still resolves; only availability flips.
        $this->assertSame('200.00', $quote['subtotal']);
    }

    // ---- 47 insufficient inventory -------------------------------------------------------------------------------------------

    public function test_insufficient_inventory_makes_quote_unavailable(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, ['total_units' => 2]);
        $plan = $this->planFor($room);
        app(HotelAvailabilityService::class)->setDay($room, '2027-03-10', ['blocked_units' => 2]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12', 1, 2, 0);

        $this->assertFalse($quote['available']);
    }

    // ---- 48 rooms in total -----------------------------------------------------------------------------------------------------

    public function test_requested_room_quantity_reflected_in_total(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12', 2, 4, 0);

        $this->assertSame('200.00', $quote['nightly'][0]['night_total']);
        $this->assertSame('400.00', $quote['subtotal']);
        $this->assertSame('400.00', $quote['total']);
    }

    // ---- 49 multi-room extras -------------------------------------------------------------------------------------------------------------------

    public function test_multiple_rooms_multiply_room_charge_correctly(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['max_adults' => 3, 'max_occupancy' => 4]);
        $plan = $this->planFor($room, ['base_rate' => '100.00', 'base_adults' => 2, 'extra_adult_rate' => '30.00']);

        // 2 rooms include 4 adults; 5th adult is the single extra.
        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 2, 5, 0);

        $this->assertSame('230.00', $quote['nightly'][0]['night_total']);
        $this->assertSame('230.00', $quote['total']);
    }

    // ---- 50 fixed stay fee ------------------------------------------------------------------------------------------------------------------------------------

    public function test_fixed_stay_fee_applied_once(): void
    {
        $property = $this->propertyFor();
        $plan = $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);
        HotelChargeRule::factory()->create([
            'property_id' => $property->id, 'name' => 'Resort Fee',
            'charge_type' => 'fee', 'calculation' => 'fixed_stay', 'value' => '20.00', 'currency' => 'USD',
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame('200.00', $quote['subtotal']);
        $this->assertSame('20.00', $quote['fees'][0]['amount']);
        $this->assertSame('220.00', $quote['total']);
    }

    // ---- 51 per-night fee --------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_per_night_fee_applied_per_night(): void
    {
        $property = $this->propertyFor();
        $plan = $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);
        HotelChargeRule::factory()->create([
            'property_id' => $property->id, 'name' => 'City Fee',
            'charge_type' => 'fee', 'calculation' => 'fixed_night', 'value' => '5.00', 'currency' => 'USD',
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame('10.00', $quote['fees'][0]['amount']);
        $this->assertSame('210.00', $quote['total']);
    }

    // ---- 52 per-room fee ----------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_per_room_fee_applied_per_room(): void
    {
        $property = $this->propertyFor();
        $plan = $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);
        HotelChargeRule::factory()->create([
            'property_id' => $property->id, 'name' => 'Cleaning Fee',
            'charge_type' => 'fee', 'calculation' => 'fixed_room', 'value' => '7.00', 'currency' => 'USD',
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12', 2, 4, 0);

        $this->assertSame('14.00', $quote['fees'][0]['amount']);
    }

    // ---- 53 percentage tax ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_percentage_tax_calculated_safely(): void
    {
        $property = $this->propertyFor();
        $plan = $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);
        HotelChargeRule::factory()->create([
            'property_id' => $property->id, 'name' => 'VAT',
            'charge_type' => 'tax', 'calculation' => 'percentage', 'value' => '10.00', 'currency' => 'USD',
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame('200.00', $quote['subtotal']);
        $this->assertSame('20.00', $quote['taxes'][0]['amount']);
        $this->assertSame('220.00', $quote['total']);
    }

    // ---- 54 no float drift ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_payment_tax_arithmetic_avoids_float_drift(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['max_adults' => 3, 'max_occupancy' => 4]);
        $property = $room->property;
        $plan = $this->planFor($room, ['base_rate' => '199.99', 'extra_adult_rate' => '0.10']);
        HotelChargeRule::factory()->create([
            'property_id' => $property->id, 'name' => 'VAT',
            'charge_type' => 'tax', 'calculation' => 'percentage', 'value' => '7.00', 'currency' => 'USD',
        ]);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11', 1, 3, 0);

        $this->assertSame('200.09', $quote['nightly'][0]['night_total']);
        $this->assertSame('14.00', $quote['taxes'][0]['amount']);
        $this->assertSame('214.09', $quote['total']);
    }

    // ---- 55 foreign rule --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_mixed_currency_foreign_rule_rejected(): void
    {
        $property = $this->propertyFor(null, ['currency' => 'USD']);

        // Foreign currency is coerced to the property currency server-side.
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.charges.store', absolute: false),
            ['property_id' => $property->id, 'name' => 'Euro Fee', 'charge_type' => 'fee', 'calculation' => 'fixed_stay', 'value' => '9.00', 'currency' => 'EUR']
        )->assertRedirect();

        $this->assertSame('USD', HotelChargeRule::where('name', 'Euro Fee')->firstOrFail()->currency);

        // Fixed charges may never pose as included-in-price.
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.charges.store', absolute: false),
            ['property_id' => $property->id, 'name' => 'Sneaky Fee', 'charge_type' => 'fee', 'calculation' => 'fixed_stay', 'value' => '9.00', 'currency' => 'USD', 'included_in_price' => true]
        )->assertStatus(422);

        $this->assertDatabaseMissing('hotel_charge_rules', ['name' => 'Sneaky Fee']);
    }

    // ---- 56 admin bulk ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_bulk_rate_update_works(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.bulk', absolute: false),
            ['hotel_rate_plan_id' => $plan->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-12', 'amount_override' => '200.00']
        )->assertRedirect();

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-13');

        $this->assertSame(['200.00', '200.00', '200.00'], array_column($quote['nightly'], 'base_rate'));
        $this->assertSame(3, HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->count());
    }

    // ---- 57 vendor bulk own ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_bulk_rate_update_own_plan(): void
    {
        $vendor = $this->makeVendor();
        $plan = $this->planFor($this->roomFor($this->propertyFor($vendor->vendorProfile)), ['base_rate' => '100.00']);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.daily-rates.bulk', absolute: false),
            ['hotel_rate_plan_id' => $plan->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-11', 'amount_override' => '140.00']
        )->assertRedirect();

        $this->assertSame('140.00', $this->pricing()->quote($plan, '2027-03-10', '2027-03-11')['nightly'][0]['base_rate']);
    }

    // ---- 58 vendor bulk foreign --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_bulk_foreign_plan_rejected(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $foreignPlan = $this->planFor($this->roomFor($this->propertyFor($vendorA->vendorProfile)));

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.daily-rates.bulk', absolute: false),
            ['hotel_rate_plan_id' => $foreignPlan->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-11', 'amount_override' => '1.00']
        )->assertNotFound();

        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 59 bulk bounded ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_bulk_range_bounded(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.bulk', absolute: false),
            ['hotel_rate_plan_id' => $plan->id, 'start_date' => '2027-01-01', 'end_date' => '2028-06-01', 'amount_override' => '200.00']
        )->assertSessionHasErrors('end_date');

        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 60 invalid range ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_invalid_date_range_rejected(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.bulk', absolute: false),
            ['hotel_rate_plan_id' => $plan->id, 'start_date' => '2027-03-14', 'end_date' => '2027-03-10', 'amount_override' => '200.00']
        )->assertSessionHasErrors('end_date');

        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 61 no internal notes ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_quote_excludes_internal_notes(): void
    {
        $property = $this->propertyFor();
        $plan = $this->planFor($this->roomFor($property));
        $this->pricing()->setDay($plan, '2027-03-10', ['amount_override' => '150.00', 'note' => 'RATESECRET999']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $this->assertStringNotContainsString('RATESECRET999', $response->getContent());
    }

    // ---- 62 no vendor internals --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_quote_excludes_vendor_internals(): void
    {
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);
        $this->planFor($this->roomFor($property));

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('vendor_profile_id', $content);
        $this->assertStringNotContainsString($vendor->vendorProfile->business_name, $content);
        $this->assertStringNotContainsString('created_by', $content);
    }

    // ---- 63 forged total ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_browser_forged_total_ignored(): void
    {
        $property = $this->propertyFor();
        $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay() + ['total' => '1.00', 'subtotal' => '1.00']))->assertOk();

        $plan = $response->json('room_types.0.plans.0');
        $this->assertSame('200.00', $plan['total']);
        $this->assertSame('200.00', $plan['subtotal']);
    }

    // ---- 64 nightly breakdown ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_quote_has_normalized_nightly_breakdown(): void
    {
        $property = $this->propertyFor();
        $this->planFor($this->roomFor($property), ['base_rate' => '100.00', 'extra_adult_rate' => '30.00', 'extra_child_rate' => '15.00']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $nightly = $response->json('room_types.0.plans.0.nightly');
        $this->assertCount(2, $nightly);

        foreach ($nightly as $night) {
            $this->assertArrayHasKey('date', $night);
            $this->assertArrayHasKey('base_rate', $night);
            $this->assertArrayHasKey('extra_adult', $night);
            $this->assertArrayHasKey('extra_child', $night);
            $this->assertArrayHasKey('night_total', $night);
        }
    }

    // ---- 65 fee/tax breakdown ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_quote_includes_mandatory_fee_tax_breakdown(): void
    {
        $property = $this->propertyFor();
        $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);
        HotelChargeRule::factory()->create([
            'property_id' => $property->id, 'name' => 'VAT',
            'charge_type' => 'tax', 'calculation' => 'percentage', 'value' => '10.00', 'currency' => 'USD',
        ]);
        HotelChargeRule::factory()->create([
            'property_id' => $property->id, 'name' => 'City Fee',
            'charge_type' => 'fee', 'calculation' => 'fixed_stay', 'value' => '5.00', 'currency' => 'USD',
        ]);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $plan = $response->json('room_types.0.plans.0');
        $this->assertSame('20.00', $plan['taxes'][0]['amount']);
        $this->assertSame('5.00', $plan['fees'][0]['amount']);
        $this->assertSame('225.00', $plan['total']);
    }

    // ---- 66 inactive property --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inactive_property_unavailable_publicly(): void
    {
        $property = $this->propertyFor(null, ['status' => PropertyStatus::Draft->value]);
        $this->planFor($this->roomFor($property));

        $this->getJson($this->ratesUrl($property, $this->stay()))->assertNotFound();
    }

    // ---- 67 inactive room ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inactive_room_type_unavailable_publicly(): void
    {
        $property = $this->propertyFor();
        $active = $this->roomFor($property, ['name' => 'Sellable Suite']);
        $this->roomFor($property, ['name' => 'Hidden Suite', 'status' => RoomTypeStatus::Draft->value]);
        $this->planFor($active);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $names = array_column($response->json('room_types'), 'name');
        $this->assertContains('Sellable Suite', $names);
        $this->assertNotContains('Hidden Suite', $names);
    }

    // ---- 68 expired plan ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_expired_rate_plan_unavailable(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $this->planFor($room, ['code' => 'old-deal', 'valid_until' => '2027-01-31']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $codes = collect($response->json('room_types'))->pluck('plans.*.code')->flatten()->all();
        $this->assertNotContains('old-deal', $codes);
    }

    // ---- 69 future plan ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_future_rate_plan_before_valid_from_unavailable(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $this->planFor($room, ['code' => 'summer-deal', 'valid_from' => '2027-06-01']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $codes = collect($response->json('room_types'))->pluck('plans.*.code')->flatten()->all();
        $this->assertNotContains('summer-deal', $codes);
    }

    // ---- 70 timezone ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_timezone_respected_for_date_rules(): void
    {
        $property = $this->propertyFor(null, ['timezone' => 'Pacific/Auckland']);
        $plan = $this->planFor($this->roomFor($property));

        $today = $this->pricing()->quote($plan, '2027-03-10', '2027-03-11');

        $this->assertTrue($today['available']);
        $this->assertSame(
            Carbon::today('Pacific/Auckland')->toDateString(),
            app(HotelAvailabilityService::class)->propertyToday($property)->toDateString()
        );
    }

    // ---- 71 audit ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_pricing_write_audited(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.rate-plans.store', absolute: false),
            $this->planPayload($room)
        )->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'hotels', 'event' => 'hotel_rate_plan.created',
        ]);

        $plan = HotelRatePlan::where('code', 'flex-room-only')->firstOrFail();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.bulk', absolute: false),
            ['hotel_rate_plan_id' => $plan->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-11', 'amount_override' => '150.00']
        )->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'hotels', 'event' => 'hotel_pricing.range_updated',
        ]);
    }

    // ---- 72 no audit spam ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_quote_does_not_audit_spam(): void
    {
        $property = $this->propertyFor();
        $this->planFor($this->roomFor($property));
        $before = ActivityLog::count();

        $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }

    // ---- 73 pricing/inventory isolation --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_pricing_does_not_mutate_inventory(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));

        $this->pricing()->applyRange($plan, '2027-03-10', '2027-03-12', ['amount_override' => '180.00']);

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 74 inventory/pricing isolation ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inventory_does_not_mutate_pricing(): void
    {
        $room = $this->roomFor($this->propertyFor());
        $this->planFor($room);

        app(HotelAvailabilityService::class)->applyRange($room, '2027-03-10', '2027-03-12', ['blocked_units' => 1]);

        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 75 custom fields isolated ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_custom_fields_cannot_affect_rate(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $plan = $this->planFor($room, ['base_rate' => '100.00']);
        $def = HotelCustomFieldDefinition::factory()->create([
            'entity_type' => 'room_type', 'field_type' => 'number', 'name' => 'Nightly Rate',
        ]);
        app(HotelCustomFieldService::class)->saveValues(
            'room_type', $room->id,
            app(HotelCustomFieldService::class)->validateValues('room_type', [$def->id => '1'])
        );

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');

        $this->assertSame('200.00', $quote['subtotal']);
    }

    // ---- 76 server ownership --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_rate_plan_server_ownership_derived(): void
    {
        $vendor = $this->makeVendor();
        $ownProperty = $this->propertyFor($vendor->vendorProfile);
        $ownRoom = $this->roomFor($ownProperty);
        $otherProperty = $this->propertyFor($vendor->vendorProfile);

        // A forged property_id in the payload cannot redirect ownership.
        $this->actingAs($vendor)->post(
            route('vendor.hotel.rate-plans.store', absolute: false),
            $this->planPayload($ownRoom, ['property_id' => $otherProperty->id, 'code' => 'vendor-flex'])
        )->assertRedirect();

        $plan = HotelRatePlan::where('code', 'vendor-flex')->firstOrFail();
        $this->assertSame($ownProperty->id, (int) $plan->property_id);
        $this->assertSame($ownRoom->id, (int) $plan->hotel_room_type_id);
    }

    // ---- 77 seeder idempotent --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_demo_seeder_pricing_data_idempotent(): void
    {
        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(3, HotelRatePlan::where('code', 'like', 'demo-%')->orWhereIn('code', ['flex-room-only', 'breakfast-included', 'non-refundable'])->count());
        $this->assertSame(1, HotelRateSeason::where('name', 'Demo Weekend Premium')->count());
        $this->assertSame(2, HotelChargeRule::where('name', 'like', 'Demo %')->count());
        $plans = HotelRatePlan::count();
        $seasons = HotelRateSeason::count();
        $daily = HotelDailyRate::count();
        $charges = HotelChargeRule::count();

        $this->seed(HotelDemoSeeder::class);
        $this->assertSame($plans, HotelRatePlan::count());
        $this->assertSame($seasons, HotelRateSeason::count());
        $this->assertSame($daily, HotelDailyRate::count());
        $this->assertSame($charges, HotelChargeRule::count());
    }

    // ---- 78 factories scale --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_factories_scale_without_unique_exhaustion(): void
    {
        HotelRatePlan::factory()->count(30)->create();
        $this->assertSame(30, HotelRatePlan::count());

        HotelRateSeason::factory()->count(20)->create();
        $this->assertSame(20, HotelRateSeason::count());

        HotelChargeRule::factory()->count(10)->create();
        $this->assertSame(10, HotelChargeRule::count());

        $plan = HotelRatePlan::firstOrFail();

        for ($i = 1; $i <= 20; $i++) {
            HotelDailyRate::factory()->create([
                'hotel_rate_plan_id' => $plan->id,
                'rate_date' => sprintf('2027-08-%02d', $i),
            ]);
        }

        $this->assertSame(20, HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->count());
    }

    // ---- 79 admin nav --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_navigation_shows_pricing_enabled(): void
    {
        $groups = AdminNavigation::filteredFor($this->makeAdmin());
        $hotels = collect($groups)->firstWhere('key', 'hotels');
        $ids = collect($hotels['items'])->pluck('id')->all();

        $this->assertContains('hotel-rate-plans', $ids);
        $this->assertContains('hotel-daily-rates', $ids);
        $this->assertContains('hotel-charges', $ids);
    }

    // ---- 80 vendor nav -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_navigation_shows_pricing_enabled(): void
    {
        $vendor = $this->makeVendor();
        $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->get(
            route('vendor.hotel.rate-plans.index', absolute: false)
        )->assertOk();

        $this->actingAs($vendor)->get(
            route('vendor.hotel.daily-rates.index', absolute: false)
        )->assertOk();
    }

    // ---- 81 nav hidden -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_pricing_nav_hidden_with_module_disabled(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $groups = AdminNavigation::filteredFor($this->makeAdmin());

        $this->assertNull(collect($groups)->firstWhere('key', 'hotels'));
    }

    // ---- 82 calendar bounded ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_pricing_calendar_only_queries_requested_range(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));
        $this->pricing()->setDay($plan, '2027-03-10', ['amount_override' => '150.00']);
        $this->pricing()->setDay($plan, '2027-04-01', ['amount_override' => '150.00']);

        $rows = $this->pricing()->calendar($plan, '2027-03-01', '2027-03-31');

        $this->assertCount(31, $rows);
        $this->assertContains('2027-03-10', array_column($rows, 'date'));
        $this->assertNotContains('2027-04-01', array_column($rows, 'date'));
    }

    // ---- 83 no per-night queries -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_quote_resolution_does_not_query_once_per_night(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
            'adjustment_type' => 'percentage', 'adjustment_value' => '10.00', 'priority' => 1,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->pricing()->quote($plan, '2027-03-10', '2027-03-15', 1, 2, 0, false);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $pricingQueries = collect($log)->filter(fn (array $entry): bool => str_contains($entry['query'], 'hotel_daily_rates')
            || str_contains($entry['query'], 'hotel_rate_seasons')
            || str_contains($entry['query'], 'hotel_charge_rules'));

        // 5 nights resolve from 3 bounded preloads — never per-night.
        $this->assertLessThanOrEqual(3, $pricingQueries->count());
    }

    // ---- 84 availability intact -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_availability_still_returns_correct_result(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, ['total_units' => 4]);
        $this->planFor($room);
        app(HotelAvailabilityService::class)->setDay($room, '2027-03-11', ['stop_sell' => true]);

        $response = $this->getJson(
            route('hotels.availability', $property->slug, absolute: false).'?'.http_build_query($this->stay())
        )->assertOk();

        $this->assertFalse($response->json('available'));
    }

    // ---- 85 room details render ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_details_still_render(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['name' => 'Harbour View Double']);
        $this->planFor($this->roomFor($property));

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()
            ->assertSee('Harbour View Double', false);
    }

    // ---- 86 available plans -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_hotel_detail_can_return_available_rate_plans(): void
    {
        $property = $this->propertyFor();
        $this->planFor($this->roomFor($property), ['code' => 'flex-room-only', 'base_rate' => '100.00']);

        $response = $this->getJson($this->ratesUrl($property, $this->stay()))->assertOk();

        $plan = $response->json('room_types.0.plans.0');
        $this->assertSame('flex-room-only', $plan['code']);
        $this->assertTrue($plan['available']);
        $this->assertSame('200.00', $plan['total']);
    }

    // ---- 87 no booking -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_no_booking_record_created_by_quote(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()));

        $this->pricing()->quote($plan, '2027-03-10', '2027-03-12');
        $this->getJson($this->ratesUrl($plan->property, $this->stay()))->assertOk();

        $this->assertSame(0, Booking::count());
        $this->assertFalse(Schema::hasTable('hotel_bookings'));
        $this->assertFalse(Schema::hasTable('hotel_reservations'));
    }

    // ---- 88 server values ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_quote_total_uses_server_values_only(): void
    {
        $property = $this->propertyFor();
        $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);

        $quote = $this->pricing()->quote(
            HotelRatePlan::firstOrFail(), '2027-03-10', '2027-03-12', 1, 2, 0, false
        );

        $this->assertSame('200.00', $quote['subtotal']);
        $this->assertSame('200.00', $quote['total']);
        $this->assertSame('USD', $quote['currency']);
    }

    // ---- 89 malformed plan -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_malformed_rate_plan_id_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.store', absolute: false),
            ['hotel_rate_plan_id' => 'not-an-id', 'rate_date' => '2027-03-10', 'amount_override' => '150.00']
        )->assertSessionHasErrors('hotel_rate_plan_id');

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.store', absolute: false),
            ['hotel_rate_plan_id' => 999999, 'rate_date' => '2027-03-10', 'amount_override' => '150.00']
        )->assertSessionHasErrors('hotel_rate_plan_id');

        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 90 snapshot seam -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_quote_snapshot_output_suitable_for_future_booking_snapshot(): void
    {
        $property = $this->propertyFor();
        $plan = $this->planFor($this->roomFor($property), ['base_rate' => '100.00']);

        $quote = $this->pricing()->quote($plan, '2027-03-10', '2027-03-12', 1, 2, 0, false);

        foreach (['property_id', 'room_type_id', 'rate_plan_id', 'currency', 'check_in', 'check_out', 'nights_count', 'rooms', 'adults', 'children', 'available', 'nightly', 'subtotal', 'taxes', 'fees', 'total', 'restrictions', 'calculated_at', 'snapshot_version'] as $key) {
            $this->assertArrayHasKey($key, $quote);
        }

        $this->assertSame(1, $quote['snapshot_version']);
        $this->assertSame(0, Booking::count());
    }

    // ---- 91 weekday bulk: no filter updates entire range (backward compatible) ----

    public function test_bulk_weekday_no_filter_updates_entire_range(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $summary = $this->pricing()->applyRange($plan, '2027-01-01', '2027-01-10', ['amount_override' => '200.00']);

        $this->assertSame(10, $summary['dates_affected']);
        $this->assertSame(10, HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->count());

        $quote = $this->pricing()->quote($plan, '2027-01-01', '2027-01-11', 1, 2, 0, false);

        $this->assertSame(array_fill(0, 10, '200.00'), array_column($quote['nightly'], 'base_rate'));
    }

    // ---- 92 weekday bulk: Saturday-only update (2027-01-01 → 2027-02-01) ----

    public function test_bulk_weekday_saturday_only_updates_saturdays(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        // 2027-01-01 is a Friday; Saturdays in range: Jan 2/9/16/23/30.
        $summary = $this->pricing()->applyRange($plan, '2027-01-01', '2027-02-01', ['amount_override' => '6500.00'], null, [6]);

        $this->assertSame(5, $summary['dates_affected']);

        $stored = HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->orderBy('rate_date')->pluck('rate_date')->all();
        $stored = array_map(fn ($d): string => substr((string) $d, 0, 10), $stored);

        $this->assertSame(['2027-01-02', '2027-01-09', '2027-01-16', '2027-01-23', '2027-01-30'], $stored);

        $this->assertSame('6500.00', $this->pricing()->quote($plan, '2027-01-02', '2027-01-03', 1, 2, 0, false)['nightly'][0]['base_rate']);
        $this->assertSame('100.00', $this->pricing()->quote($plan, '2027-01-03', '2027-01-04', 1, 2, 0, false)['nightly'][0]['base_rate']);
    }

    // ---- 93 weekday bulk: Sat+Sun updates only matching days ----

    public function test_bulk_weekday_sat_sun_updates_matching_days(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $summary = $this->pricing()->applyRange($plan, '2027-01-01', '2027-01-10', ['amount_override' => '300.00'], null, [0, 6]);

        $this->assertSame(4, $summary['dates_affected']);

        $stored = HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->orderBy('rate_date')->pluck('rate_date')->all();
        $stored = array_map(fn ($d): string => substr((string) $d, 0, 10), $stored);

        $this->assertSame(['2027-01-02', '2027-01-03', '2027-01-09', '2027-01-10'], $stored);
    }

    // ---- 94 weekday bulk: unmatched weekdays remain untouched ----

    public function test_bulk_weekday_unmatched_days_untouched(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $this->pricing()->setDay($plan, '2027-01-04', ['amount_override' => '111.00']);

        $this->pricing()->applyRange($plan, '2027-01-01', '2027-01-10', ['amount_override' => '6500.00'], null, [6]);

        // Pre-existing Monday override survives; Monday quotes unchanged.
        $this->assertSame('111.00', $this->pricing()->quote($plan, '2027-01-04', '2027-01-05', 1, 2, 0, false)['nightly'][0]['base_rate']);
        $this->assertSame('100.00', $this->pricing()->quote($plan, '2027-01-05', '2027-01-06', 1, 2, 0, false)['nightly'][0]['base_rate']);
        $this->assertDatabaseHas('hotel_daily_rates', ['hotel_rate_plan_id' => $plan->id, 'rate_date' => '2027-01-04']);
    }

    // ---- 95 weekday bulk: clear with Saturday selected clears Saturdays only ----

    public function test_bulk_weekday_clear_saturday_only(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $this->pricing()->applyRange($plan, '2027-01-01', '2027-01-10', ['amount_override' => '200.00']);
        $this->assertSame(10, HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->count());

        $deleted = $this->pricing()->clearRange($plan, '2027-01-01', '2027-01-10', null, [6]);

        $this->assertSame(2, $deleted);

        $remaining = HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->orderBy('rate_date')->pluck('rate_date')->all();
        $remaining = array_map(fn ($d): string => substr((string) $d, 0, 10), $remaining);

        $this->assertNotContains('2027-01-02', $remaining);
        $this->assertNotContains('2027-01-09', $remaining);
        $this->assertContains('2027-01-03', $remaining);
        $this->assertContains('2027-01-04', $remaining);
        $this->assertCount(8, $remaining);
    }

    // ---- 96 weekday bulk: clear without selection keeps whole-range behavior ----

    public function test_bulk_weekday_clear_without_selection_clears_all(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        $this->pricing()->applyRange($plan, '2027-01-01', '2027-01-10', ['amount_override' => '200.00']);

        $deleted = $this->pricing()->clearRange($plan, '2027-01-01', '2027-01-10');

        $this->assertSame(10, $deleted);
        $this->assertSame(0, HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->count());
    }

    // ---- 97 weekday bulk: invalid weekday rejected ----

    public function test_bulk_weekday_invalid_rejected(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.daily-rates.bulk', absolute: false),
            ['hotel_rate_plan_id' => $plan->id, 'start_date' => '2027-01-01', 'end_date' => '2027-01-10', 'amount_override' => '200.00', 'weekdays' => [9]]
        )->assertSessionHasErrors('weekdays.0');

        $this->assertSame(0, HotelDailyRate::count());

        try {
            $this->pricing()->applyRange($plan, '2027-01-01', '2027-01-10', ['amount_override' => '200.00'], null, [7]);
            $this->fail('Expected invalid weekday to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('weekdays', $e->errors());
        }

        $this->assertSame(0, HotelDailyRate::count());
    }

    // ---- 98 weekday bulk: daily override still beats seasonal/base ----

    public function test_bulk_weekday_daily_override_beats_season(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);
        HotelRateSeason::factory()->create([
            'hotel_rate_plan_id' => $plan->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-02-01',
            'adjustment_type' => 'percentage', 'adjustment_value' => '20.00', 'priority' => 1,
        ]);

        $this->pricing()->applyRange($plan, '2027-01-01', '2027-02-01', ['amount_override' => '6500.00'], null, [6]);

        // Saturday: daily override wins over the +20% season.
        $this->assertSame('6500.00', $this->pricing()->quote($plan, '2027-01-02', '2027-01-03', 1, 2, 0, false)['nightly'][0]['base_rate']);
        // Monday: seasonal rule still applies over the base rate.
        $this->assertSame('120.00', $this->pricing()->quote($plan, '2027-01-04', '2027-01-05', 1, 2, 0, false)['nightly'][0]['base_rate']);
    }

    // ---- 99 weekday bulk: stop-sell/CTA/CTD work with weekday filter ----

    public function test_bulk_weekday_restrictions_apply_with_filter(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $this->pricing()->applyRange(
            $plan, '2027-01-01', '2027-01-10',
            ['stop_sell' => true, 'closed_to_arrival' => true, 'closed_to_departure' => true, 'note' => 'Weekend closed'],
            null, [6]
        );

        $saturday = HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->where('rate_date', '2027-01-02')->firstOrFail();
        $this->assertTrue((bool) $saturday->stop_sell);
        $this->assertTrue((bool) $saturday->closed_to_arrival);
        $this->assertTrue((bool) $saturday->closed_to_departure);
        $this->assertSame('Weekend closed', $saturday->note);

        $this->assertDatabaseMissing('hotel_daily_rates', ['hotel_rate_plan_id' => $plan->id, 'rate_date' => '2027-01-04']);

        $quote = $this->pricing()->quote($plan, '2027-01-02', '2027-01-03');

        $this->assertFalse($quote['available']);
    }

    // ---- 100 weekday bulk: sparse storage stays sparse ----

    public function test_bulk_weekday_sparse_storage(): void
    {
        $plan = $this->planFor($this->roomFor($this->propertyFor()), ['base_rate' => '100.00']);

        $summary = $this->pricing()->applyRange($plan, '2027-01-01', '2027-02-01', ['amount_override' => '6500.00'], null, [6]);

        $this->assertSame(5, $summary['dates_affected']);
        $this->assertSame(5, $summary['rows_stored']);
        $this->assertSame(5, HotelDailyRate::where('hotel_rate_plan_id', $plan->id)->count());
    }
}
