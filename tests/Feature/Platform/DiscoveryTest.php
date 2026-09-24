<?php

namespace Tests\Feature\Platform;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\City;
use App\Models\Destination;
use App\Models\ExchangeRate;
use App\Models\HotelAmenity;
use App\Models\HotelRatePlan;
use App\Models\HotelReview;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\Language;
use App\Models\Place;
use App\Models\Property;
use App\Models\Setting;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Services\CurrencyConversionService;
use App\Support\CurrencyRegistry;
use App\Support\Localization;
use App\Support\ModuleManager;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 13C unified search & discovery foundation.
 *
 * Focused contract tests only — one exact method per behavior.
 */
class DiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        $this->seed(LanguageSeeder::class);
        $this->seed(CurrencySeeder::class);
        app(ModuleManager::class)->setEnabled('hotels', true);
    }

    protected function enableHotels(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', true);
    }

    protected function disableHotels(): void
    {
        Setting::setValue('modules.hotels.enabled', '0');
    }

    protected function disableTours(): void
    {
        Setting::setValue('modules.tours.enabled', '0');
    }

    protected function disableTaxi(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');
    }

    protected function makeProperty(City $city, string $name, string $slug, array $overrides = []): Property
    {
        return Property::factory()->create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
            'city_id' => $city->id,
            'currency' => 'USD',
        ], $overrides));
    }

    protected function makeRoomWithPlan(Property $property, string $rate = '200.00'): HotelRoomType
    {
        $room = HotelRoomType::factory()->create([
            'property_id' => $property->id,
            'status' => RoomTypeStatus::Active->value,
            'total_units' => 5,
            'max_adults' => 4,
            'max_children' => 2,
            'max_occupancy' => 4,
        ]);

        HotelRatePlan::factory()->create([
            'property_id' => $property->id,
            'hotel_room_type_id' => $room->id,
            'currency' => 'USD',
            'base_rate' => $rate,
            'is_active' => true,
        ]);

        return $room;
    }

    protected function setRate(string $quote, string $rate): void
    {
        ExchangeRate::updateOrCreate(
            ['base_currency_code' => CurrencyConversionService::fxBase(), 'quote_currency_code' => $quote],
            ['rate' => $rate, 'source' => 'manual', 'fetched_at' => now()]
        );
        CurrencyRegistry::forgetCache();
    }

    // ---- Location autocomplete ----

    public function test_empty_query_is_bounded_safe(): void
    {
        $this->getJson('/discover/locations?q=')->assertOk()->assertJsonPath('suggestions', []);
        $this->getJson('/discover/locations')->assertOk()->assertJsonPath('suggestions', []);
    }

    public function test_minimum_query_length_blocks_single_char(): void
    {
        City::factory()->create(['name' => 'Jaipur Alpha', 'slug' => 'jaipur-alpha-1']);

        $this->getJson('/discover/locations?q=J')->assertOk()->assertJsonPath('suggestions', []);
    }

    public function test_city_found_with_normalized_type(): void
    {
        $city = City::factory()->create(['name' => 'Jaipur Discovery', 'slug' => 'jaipur-discovery-1']);

        $response = $this->getJson('/discover/locations?q=Jaipur%20Discovery');

        $response->assertOk();
        $row = collect($response->json('suggestions'))->firstWhere('id', $city->id);
        $this->assertNotNull($row);
        $this->assertSame('city', $row['type']);
        $this->assertSame('/cities/jaipur-discovery-1', $row['url']);
    }

    public function test_destination_found_distinct_from_city(): void
    {
        $destination = Destination::factory()->create(['name' => 'Amber Fort Area Test', 'slug' => 'amber-fort-area-test-1']);

        $response = $this->getJson('/discover/locations?q=Amber%20Fort%20Area');

        $response->assertOk();
        $row = collect($response->json('suggestions'))->firstWhere('id', $destination->id);
        $this->assertNotNull($row);
        $this->assertSame('destination', $row['type']);
        $this->assertNotSame('city', $row['type']);
    }

    public function test_place_found(): void
    {
        $place = Place::factory()->create(['name' => 'Hawa Mahal View Test', 'slug' => 'hawa-mahal-view-test-1']);

        $response = $this->getJson('/discover/locations?q=Hawa%20Mahal%20View');

        $response->assertOk();
        $row = collect($response->json('suggestions'))->firstWhere('id', $place->id);
        $this->assertNotNull($row);
        $this->assertSame('place', $row['type']);
    }

    public function test_published_property_found(): void
    {
        $city = City::factory()->create(['name' => 'Property City X', 'slug' => 'property-city-x-1']);
        $property = $this->makeProperty($city, 'Harbour Discovery Suites', 'harbour-discovery-suites-1');

        $response = $this->getJson('/discover/locations?q=Harbour%20Discovery');

        $response->assertOk();
        $row = collect($response->json('suggestions'))->firstWhere('id', $property->id);
        $this->assertNotNull($row);
        $this->assertSame('property', $row['type']);
    }

    public function test_inactive_city_excluded(): void
    {
        City::factory()->inactive()->create(['name' => 'Ghost City Hidden', 'slug' => 'ghost-city-hidden-1']);

        $this->getJson('/discover/locations?q=Ghost%20City%20Hidden')
            ->assertOk()->assertJsonPath('suggestions', []);
    }

    public function test_inactive_destination_excluded(): void
    {
        Destination::factory()->inactive()->create(['name' => 'Ghost Destination Hidden', 'slug' => 'ghost-destination-hidden-1']);

        $this->getJson('/discover/locations?q=Ghost%20Destination%20Hidden')
            ->assertOk()->assertJsonPath('suggestions', []);
    }

    public function test_inactive_place_excluded(): void
    {
        Place::factory()->create(['name' => 'Ghost Place Hidden', 'slug' => 'ghost-place-hidden-1', 'is_active' => false]);

        $this->getJson('/discover/locations?q=Ghost%20Place%20Hidden')
            ->assertOk()->assertJsonPath('suggestions', []);
    }

    public function test_unpublished_property_excluded(): void
    {
        $city = City::factory()->create(['name' => 'Draft City X', 'slug' => 'draft-city-x-1']);
        Property::factory()->create([
            'name' => 'Draft Discovery Hotel', 'slug' => 'draft-discovery-hotel-1',
            'status' => PropertyStatus::Draft->value, 'city_id' => $city->id,
        ]);

        $this->getJson('/discover/locations?q=Draft%20Discovery')
            ->assertOk()->assertJsonPath('suggestions', []);
    }

    public function test_hotels_disabled_excludes_property_but_geography_remains(): void
    {
        $city = City::factory()->create(['name' => 'Gated City Jaipur', 'slug' => 'gated-city-jaipur-1']);
        $this->makeProperty($city, 'Gated Discovery Hotel', 'gated-discovery-hotel-1');
        $this->disableHotels();

        $response = $this->getJson('/discover/locations?q=Gated');
        $response->assertOk();
        $types = collect($response->json('suggestions'))->pluck('type')->all();
        $this->assertNotContains('property', $types);
        $this->assertContains('city', $types);
    }

    public function test_exact_match_ranks_before_contains(): void
    {
        $city = City::factory()->create(['name' => 'Rangpur Test', 'slug' => 'rangpur-test-1']);
        City::factory()->create(['name' => 'Old Rangpur Test Suburb', 'slug' => 'old-rangpur-test-suburb-1']);

        $response = $this->getJson('/discover/locations?q=Rangpur%20Test');
        $response->assertOk();
        $suggestions = $response->json('suggestions');
        $this->assertNotEmpty($suggestions);
        $this->assertSame($city->id, $suggestions[0]['id']);
    }

    public function test_result_limit_enforced(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            City::factory()->create(['name' => "Limitville Test {$i}", 'slug' => "limitville-test-{$i}"]);
        }

        $response = $this->getJson('/discover/locations?q=Limitville%20Test&limit=10');
        $response->assertOk();
        $this->assertLessThanOrEqual(10, count($response->json('suggestions')));
    }

    public function test_localized_name_returned_with_fallback(): void
    {
        $destination = Destination::factory()->create(['name' => 'Original Discovery Name', 'slug' => 'original-discovery-name-1']);
        $destination->setTranslation('hi', 'name', 'हिंदी खोज नाम');

        $response = $this->getJson('/discover/locations?q=Original%20Discovery');
        $response->assertOk();
        $row = collect($response->json('suggestions'))->firstWhere('id', $destination->id);
        $this->assertNotNull($row);
        // Default locale (en) has no translation → original fallback, never blank.
        $this->assertSame('Original Discovery Name', $row['name']);
    }

    public function test_arabic_query_works_safely(): void
    {
        $destination = Destination::factory()->create(['name' => 'مكة المكرمة للاختبار', 'slug' => 'makkah-test-1']);

        $response = $this->getJson('/discover/locations?q='.urlencode('مكة المكرمة'));
        $response->assertOk();
        $row = collect($response->json('suggestions'))->firstWhere('id', $destination->id);
        $this->assertNotNull($row);
    }

    public function test_payload_contains_no_private_fields(): void
    {
        $city = City::factory()->create(['name' => 'Private City Check', 'slug' => 'private-city-check-1']);
        $property = $this->makeProperty($city, 'Private Check Hotel', 'private-check-hotel-1', [
            'email' => 'owner@example.com', 'phone' => '+10000000000',
        ]);

        $response = $this->getJson('/discover/locations?q=Private%20Check');
        $response->assertOk();
        $payload = json_encode($response->json('suggestions'));
        $this->assertStringNotContainsString('owner@example.com', (string) $payload);
        $this->assertStringNotContainsString('+10000000000', (string) $payload);
        $this->assertStringNotContainsString('vendor_profile_id', (string) $payload);
        $this->assertNotNull(collect($response->json('suggestions'))->firstWhere('id', $property->id));
    }

    // ---- Hotel search ----

    public function test_hotel_location_filter_by_city(): void
    {
        $cityA = City::factory()->create(['name' => 'Hotel City A', 'slug' => 'hotel-city-a-1']);
        $cityB = City::factory()->create(['name' => 'Hotel City B', 'slug' => 'hotel-city-b-1']);
        $inA = $this->makeProperty($cityA, 'City A Hotel', 'city-a-hotel-1');
        $this->makeProperty($cityB, 'City B Hotel', 'city-b-hotel-1');

        $response = $this->getJson("/search/hotels?location_type=city&location_id={$cityA->id}");
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($inA->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_hotel_destination_filter(): void
    {
        $destination = Destination::factory()->create(['name' => 'Hotel Dest X', 'slug' => 'hotel-dest-x-1']);
        $property = $this->makeProperty($destination->city, 'Dest X Hotel', 'dest-x-hotel-1', ['destination_id' => $destination->id]);

        $response = $this->getJson("/search/hotels?location_type=destination&location_id={$destination->id}");
        $response->assertOk();
        $this->assertContains($property->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_hotel_property_type_filter(): void
    {
        $city = City::factory()->create(['name' => 'Type City X', 'slug' => 'type-city-x-1']);
        $property = $this->makeProperty($city, 'Type Filter Hotel', 'type-filter-hotel-1');

        $response = $this->getJson('/search/hotels?property_type_id='.$property->property_type_id);
        $response->assertOk();
        $this->assertContains($property->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_hotel_amenity_filter(): void
    {
        $city = City::factory()->create(['name' => 'Amenity City X', 'slug' => 'amenity-city-x-1']);
        $property = $this->makeProperty($city, 'Amenity Hotel', 'amenity-hotel-1');
        $amenity = HotelAmenity::factory()->create(['is_active' => true]);
        $property->amenities()->attach($amenity->id);

        $response = $this->getJson('/search/hotels?amenities[]='.$amenity->id);
        $response->assertOk();
        $this->assertContains($property->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_hotel_rating_filter_uses_approved_only(): void
    {
        $approved = HotelReview::factory()->approved()->fiveStar()->create();
        $approvedProperty = $approved->property;
        $pending = HotelReview::factory()->pending()->fiveStar()->create();
        $pendingProperty = $pending->property;

        // Publish both (factory properties may be drafts).
        $approvedProperty->forceFill(['status' => PropertyStatus::Published->value])->save();
        $pendingProperty->forceFill(['status' => PropertyStatus::Published->value])->save();
        $approvedProperty->refresh();
        $pendingProperty->refresh();

        $this->assertSame('5.00', (string) $approvedProperty->rating_average);

        $response = $this->getJson('/search/hotels?min_rating=4');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($approvedProperty->id, $ids);
        $this->assertNotContains($pendingProperty->id, $ids);
    }

    public function test_hotel_date_validation_rejects_bad_range(): void
    {
        $this->getJson('/search/hotels?check_in=2026-05-10&check_out=2026-05-09')
            ->assertStatus(422);
    }

    public function test_hotel_availability_excludes_stop_sell(): void
    {
        $city = City::factory()->create(['name' => 'Avail City X', 'slug' => 'avail-city-x-1']);
        $property = $this->makeProperty($city, 'Avail Hotel', 'avail-hotel-1');
        $room = $this->makeRoomWithPlan($property);
        $in = now()->addDays(10)->toDateString();
        $out = now()->addDays(12)->toDateString();

        HotelRoomInventory::factory()->create([
            'hotel_room_type_id' => $room->id,
            'inventory_date' => now()->addDays(10)->toDateString(),
            'stop_sell' => true,
        ]);

        $response = $this->getJson("/search/hotels?location_type=city&location_id={$city->id}&check_in={$in}&check_out={$out}");
        $response->assertOk();
        $this->assertNotContains($property->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_hotel_price_sorting_works(): void
    {
        $city = City::factory()->create(['name' => 'Sort City X', 'slug' => 'sort-city-x-1']);
        $cheap = $this->makeProperty($city, 'Cheap Hotel', 'cheap-hotel-1');
        $this->makeRoomWithPlan($cheap, '100.00');
        $pricey = $this->makeProperty($city, 'Pricey Hotel', 'pricey-hotel-1');
        $this->makeRoomWithPlan($pricey, '500.00');

        $response = $this->getJson("/search/hotels?location_type=city&location_id={$city->id}&sort=price_asc");
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$cheap->id, $pricey->id], $ids);
    }

    public function test_hotel_pagination_bounded(): void
    {
        $city = City::factory()->create(['name' => 'Page City X', 'slug' => 'page-city-x-1']);

        for ($i = 1; $i <= 5; $i++) {
            $this->makeProperty($city, "Paged Hotel {$i}", "paged-hotel-{$i}");
        }

        $response = $this->getJson("/search/hotels?location_type=city&location_id={$city->id}&per_page=2&page=2");
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertSame(5, $response->json('meta.total'));
    }

    public function test_hotel_invalid_sort_rejected(): void
    {
        $this->getJson('/search/hotels?sort=price;DROP%20TABLE%20properties')
            ->assertStatus(422);
    }

    public function test_hotel_display_currency_does_not_change_authoritative_price(): void
    {
        $city = City::factory()->create(['name' => 'FX City X', 'slug' => 'fx-city-x-1']);
        $property = $this->makeProperty($city, 'FX Hotel', 'fx-hotel-1');
        $this->makeRoomWithPlan($property, '200.00');
        $this->setRate('INR', '83.00');

        $response = $this->withSession(['currency' => 'INR'])
            ->getJson("/search/hotels?location_type=city&location_id={$city->id}");
        $response->assertOk();
        $card = collect($response->json('data'))->firstWhere('id', $property->id);
        $this->assertSame('200.00', $card['starting_price']['amount']);
        $this->assertSame('USD', $card['starting_price']['currency']);
        $this->assertSame('INR', $card['display_money']['display_currency']);
    }

    public function test_hotel_module_disabled_gates_search(): void
    {
        $this->disableHotels();

        $this->getJson('/search/hotels')->assertStatus(404);
    }

    // ---- Tour search ----

    public function test_tour_city_filter(): void
    {
        $city = City::factory()->create(['name' => 'Tour City X', 'slug' => 'tour-city-x-1']);
        $tour = TourPackage::factory()->approved()->create(['city_id' => $city->id, 'title' => 'Tour City Special', 'slug' => 'tour-city-special-1']);

        $response = $this->getJson("/search/tours?location_type=city&location_id={$city->id}");
        $response->assertOk();
        $this->assertContains($tour->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_tour_category_filter(): void
    {
        $category = TourCategory::factory()->create();
        $tour = TourPackage::factory()->approved()->create(['category_id' => $category->id]);

        $response = $this->getJson('/search/tours?category_id='.$category->id);
        $response->assertOk();
        $this->assertContains($tour->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_tour_inactive_excluded(): void
    {
        $tour = TourPackage::factory()->inactive()->create(['title' => 'Hidden Tour X', 'slug' => 'hidden-tour-x-1']);

        $response = $this->getJson('/search/tours?q=Hidden%20Tour');
        $response->assertOk();
        $this->assertNotContains($tour->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_tour_display_currency_presentation_only(): void
    {
        $tour = TourPackage::factory()->approved()->create(['price' => 10000, 'discounted_price' => null]);
        $this->setRate('INR', '83.00');

        $response = $this->withSession(['currency' => 'USD'])
            ->getJson('/search/tours?q='.urlencode(mb_substr($tour->title, 0, 12)));
        $response->assertOk();
        $card = collect($response->json('data'))->firstWhere('id', $tour->id);
        $this->assertNotNull($card);
        $this->assertSame('INR', $card['starting_price']['currency']);
        $this->assertSame('USD', $card['display_money']['display_currency']);
        $this->assertEquals(10000, TourPackage::find($tour->id)->price);
    }

    public function test_tours_module_gating_respected(): void
    {
        $this->disableTours();

        $this->getJson('/search/tours')->assertStatus(404);
        // Geography stays shared.
        City::factory()->create(['name' => 'Still City X', 'slug' => 'still-city-x-1']);
        $this->getJson('/discover/locations?q=Still%20City')->assertOk();
    }

    // ---- Taxi discovery ----

    public function test_taxi_contract_preserves_operational_model(): void
    {
        $response = $this->getJson('/discover/taxi?q=Jaipur');
        $response->assertOk();
        $fields = collect($response->json('operational_fields'))->pluck('name')->all();
        foreach (['pickup_address', 'drop_address', 'pickup_at', 'passenger_count'] as $required) {
            $this->assertContains($required, $fields);
        }
        $this->assertFalse($response->json('supports_airport_entity'));
    }

    public function test_taxi_disabled_gates_but_geography_remains(): void
    {
        $this->disableTaxi();

        $this->getJson('/discover/taxi?q=Jaipur')->assertStatus(404);
        City::factory()->create(['name' => 'Taxi City X', 'slug' => 'taxi-city-x-1']);
        $this->getJson('/discover/locations?q=Taxi%20City')->assertOk();
    }

    // ---- DTO / facets / security ----

    public function test_hotel_card_compact_no_gallery_dump(): void
    {
        $city = City::factory()->create(['name' => 'Compact City X', 'slug' => 'compact-city-x-1']);
        $property = $this->makeProperty($city, 'Compact Hotel', 'compact-hotel-1');
        $this->makeRoomWithPlan($property);

        $response = $this->getJson("/search/hotels?location_type=city&location_id={$city->id}");
        $response->assertOk();
        $card = collect($response->json('data'))->firstWhere('id', $property->id);
        $this->assertArrayNotHasKey('gallery', $card);
        $this->assertArrayNotHasKey('rooms', $card);
        $this->assertArrayNotHasKey('email', $card);
        $this->assertArrayHasKey('display_money', $card);
    }

    public function test_hotel_facets_use_public_entities_only(): void
    {
        $city = City::factory()->create(['name' => 'Facet City X', 'slug' => 'facet-city-x-1']);
        $property = $this->makeProperty($city, 'Facet Hotel', 'facet-hotel-1');
        $this->makeRoomWithPlan($property);
        $this->makeProperty($city, 'Facet Draft', 'facet-draft-1', ['status' => PropertyStatus::Draft->value]);

        $response = $this->getJson("/search/hotels?location_type=city&location_id={$city->id}");
        $response->assertOk();
        $facets = $response->json('facets');
        $this->assertNotEmpty($facets['property_types']);
        $total = array_sum(array_column($facets['property_types'], 'count'));
        $this->assertSame(1, $total);
    }

    public function test_arbitrary_sort_cannot_inject_sql(): void
    {
        $this->getJson('/search/tours?sort=(select%20sleep(5))')->assertStatus(422);
    }

    public function test_unpublished_entities_never_public(): void
    {
        $draft = TourPackage::factory()->draft()->create(['title' => 'Secret Draft Tour', 'slug' => 'secret-draft-tour-1']);

        $this->getJson('/search/tours?q=Secret%20Draft')->assertOk();
        $this->assertNotContains(
            $draft->id,
            collect($this->getJson('/search/tours?q=Secret%20Draft')->json('data'))->pluck('id')->all()
        );
        $this->getJson('/discover/location?type=city&id=999999')->assertStatus(404);
    }

    // ---- Localization / currency ----

    public function test_current_locale_reflected_and_fallback_safe(): void
    {
        $destination = Destination::factory()->create(['name' => 'Locale Original', 'slug' => 'locale-original-1']);
        $destination->setTranslation('hi', 'name', 'लोकेल नाम');

        $this->assertSame('लोकेल नाम', $destination->fresh()->translated('name', 'hi'));
        $this->assertSame('Locale Original', $destination->fresh()->translated('name', 'fr'));
    }

    public function test_missing_fx_falls_back_to_source_currency(): void
    {
        $city = City::factory()->create(['name' => 'No FX City', 'slug' => 'no-fx-city-1']);
        $property = $this->makeProperty($city, 'No FX Hotel', 'no-fx-hotel-1');
        $this->makeRoomWithPlan($property, '150.00');

        $response = $this->withSession(['currency' => 'EUR'])
            ->getJson("/search/hotels?location_type=city&location_id={$city->id}");
        $response->assertOk();
        $card = collect($response->json('data'))->firstWhere('id', $property->id);
        $this->assertSame('150.00', $card['starting_price']['amount']);
        $this->assertNotEmpty($card['display_money']['display_currency']);
    }

    public function test_rtl_locale_does_not_alter_identifiers(): void
    {
        Language::query()->where('code', 'ar')->update(['is_active' => true]);
        Localization::forgetCache();

        $city = City::factory()->create(['name' => 'RTL City X', 'slug' => 'rtl-city-x-1']);

        $response = $this->withSession([Localization::SESSION_KEY => 'ar'])
            ->getJson('/discover/locations?q=RTL%20City');
        $response->assertOk();
        $row = collect($response->json('suggestions'))->firstWhere('id', $city->id);
        $this->assertSame('city', $row['type']);
        $this->assertSame($city->id, $row['id']);
    }

    // ---- Landing contracts ----

    public function test_city_landing_contract_distinct(): void
    {
        $city = City::factory()->create(['name' => 'Landing City X', 'slug' => 'landing-city-x-1']);

        $response = $this->getJson("/discover/location?type=city&id={$city->id}");
        $response->assertOk()->assertJsonPath('kind', 'city');
        $this->assertArrayHasKey('hotels', $response->json());
        $this->assertArrayHasKey('destinations', $response->json());
        $this->assertFalse($response->json('seo.noindex'));
    }

    public function test_destination_contract_distinct_from_city(): void
    {
        $destination = Destination::factory()->create(['name' => 'Landing Dest X', 'slug' => 'landing-dest-x-1']);

        $response = $this->getJson("/discover/location?type=destination&id={$destination->id}");
        $response->assertOk()->assertJsonPath('kind', 'destination');
        $this->assertArrayHasKey('destination_type', $response->json());
    }

    public function test_place_contract_distinct(): void
    {
        $place = Place::factory()->create(['name' => 'Landing Place X', 'slug' => 'landing-place-x-1']);

        $response = $this->getJson("/discover/location?type=place&id={$place->id}");
        $response->assertOk()->assertJsonPath('kind', 'place');
        $this->assertArrayHasKey('tours', $response->json());
    }

    public function test_search_pages_are_noindex_with_canonical(): void
    {
        $this->getJson('/search/hotels?sort=recommended')
            ->assertOk()->assertJsonPath('seo.noindex', true);
        $this->assertStringContainsString(
            '/search/hotels',
            (string) $this->getJson('/search/hotels')->json('seo.canonical')
        );
    }
}
