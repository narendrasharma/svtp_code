<?php

namespace Tests\Feature\Shared;

use App\Enums\PropertyStatus;
use App\Http\Middleware\EnsureStaffPermission;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Country;
use App\Models\Destination;
use App\Models\HotelCustomFieldDefinition;
use App\Models\HotelCustomFieldValue;
use App\Models\HotelRoomType;
use App\Models\Place;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\State;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelCustomFieldService;
use App\Services\HotelPropertyService;
use App\Services\LocationHierarchy;
use App\Services\TravelLocationService;
use App\Support\AdminNavigation;
use App\Support\ModuleManager;
use App\Support\StaffPermissions;
use Database\Seeders\HotelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Shared geography & destination foundation (12B.4.1).
 *
 * Covers Country/State/City consistency, Destination/Place
 * generalization, Property integration, Tour compatibility, Taxi
 * no-regression, dependent selects, discovery seams and hierarchy
 * security — one exact method per behavior (memory-safe runner).
 */
class GeographyTest extends TestCase
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

    protected function makeStaff(string $role = 'support-agent'): User
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff->fresh();
    }

    protected function makeVendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        return $vendor->fresh();
    }

    protected function propertyTypeId(): int
    {
        return (int) PropertyType::where('slug', 'hotel')->firstOrFail()->id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function propertyPayload(array $overrides = []): array
    {
        return array_merge([
            'property_type_id' => $this->propertyTypeId(),
            'name' => 'Geography Test Hotel',
            'address_line_1' => '12 Test Street',
            'country_code' => 'US',
        ], $overrides);
    }

    protected function createProperty(array $overrides = [], ?User $actor = null): Property
    {
        return app(HotelPropertyService::class)->create(
            $this->propertyPayload($overrides),
            $actor ?? $this->makeAdmin(),
            null,
            (bool) ($overrides['publish_directly'] ?? false)
        );
    }

    protected function createTour(City $city, string $title, string $slug, bool $isActive = true): TourPackage
    {
        return TourPackage::create([
            'title' => $title,
            'slug' => $slug,
            'city_id' => $city->id,
            'duration_days' => 1,
            'duration_nights' => 0,
            'price' => 2500,
            'is_active' => $isActive,
            'moderation_status' => 'approved',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function countryPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Testland',
            'iso2' => 'TL',
            'iso3' => 'TLD',
            'phone_code' => '+999',
            'currency_code' => 'TLR',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides);
    }

    // ---- 1 admin can access country management ------------------------

    public function test_admin_can_access_country_management(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.countries.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('admin.states.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('admin.cities.index', absolute: false))->assertOk();
    }

    // ---- 2 unauthorized staff blocked ----------------------------------

    public function test_unauthorized_staff_blocked_from_country_management(): void
    {
        $staff = $this->makeStaff('support-agent');

        $this->actingAs($staff)->get(route('admin.countries.index', absolute: false))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.countries.store', absolute: false), $this->countryPayload())->assertForbidden();
    }

    // ---- 3 vendor cannot manage countries -------------------------------

    public function test_vendor_cannot_manage_countries(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($vendor)->get(route('admin.countries.index', absolute: false))->assertForbidden();
        $this->actingAs($vendor)->post(route('admin.countries.store', absolute: false), $this->countryPayload())->assertForbidden();
    }

    // ---- 4 country create -------------------------------------------------

    public function test_admin_can_create_country(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.countries.store', absolute: false),
            $this->countryPayload(['name' => 'Ruritania', 'iso2' => 'ru'])
        )->assertRedirect(route('admin.countries.index', absolute: false));

        $this->assertDatabaseHas('countries', ['name' => 'Ruritania', 'iso2' => 'RU']);
    }

    // ---- 5 unique ISO2 validation ------------------------------------------

    public function test_country_iso2_must_be_unique(): void
    {
        Country::factory()->create(['iso2' => 'TL', 'name' => 'First Testland']);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.countries.store', absolute: false),
            $this->countryPayload(['iso2' => 'TL'])
        )->assertSessionHasErrors('iso2');
    }

    // ---- 6 state belongs to country ------------------------------------------

    public function test_admin_can_create_state_under_country(): void
    {
        $country = Country::factory()->create();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.states.store', absolute: false),
            ['country_id' => $country->id, 'name' => 'North Testshire', 'slug' => 'north-testshire', 'is_active' => true]
        )->assertRedirect(route('admin.states.index', absolute: false));

        $this->assertDatabaseHas('states', ['slug' => 'north-testshire', 'country_id' => $country->id]);
    }

    // ---- 7 state from another country has no shortcut ----------------------------

    public function test_state_country_relation_is_stored(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);

        $this->assertSame($country->id, $state->fresh()->country_id);
        $this->assertTrue($state->country->is($country));
    }

    // ---- 8 city belongs to country ----------------------------------------

    public function test_admin_can_create_city_under_country_without_state(): void
    {
        $country = Country::factory()->create();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $country->id, 'name' => 'Port Testville', 'slug' => 'port-testville', 'is_active' => true]
        )->assertRedirect(route('admin.cities.index', absolute: false));

        $this->assertDatabaseHas('cities', ['slug' => 'port-testville', 'country_id' => $country->id, 'state_id' => null]);
    }

    // ---- 9 city may belong to state ----------------------------------------

    public function test_admin_can_create_city_with_state(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $country->id, 'state_id' => $state->id, 'name' => 'Mill Testville', 'slug' => 'mill-testville', 'is_active' => true]
        )->assertRedirect(route('admin.cities.index', absolute: false));

        $this->assertDatabaseHas('cities', ['slug' => 'mill-testville', 'state_id' => $state->id]);
    }

    // ---- 10 city with state from another country rejected ------------------

    public function test_city_with_state_from_another_country_rejected(): void
    {
        $countryA = Country::factory()->create();
        $countryB = Country::factory()->create();
        $foreignState = State::factory()->create(['country_id' => $countryB->id]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $countryA->id, 'state_id' => $foreignState->id, 'name' => 'Bad Mix City', 'slug' => 'bad-mix-city', 'is_active' => true]
        )->assertSessionHasErrors('state_id');

        $this->assertDatabaseMissing('cities', ['slug' => 'bad-mix-city']);
    }

    // ---- 11 same city name in different countries ----------------------------

    public function test_same_city_name_allowed_in_different_countries(): void
    {
        $countryA = Country::factory()->create();
        $countryB = Country::factory()->create();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $countryA->id, 'name' => 'Springfield', 'slug' => 'springfield-a', 'is_active' => true]
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $countryB->id, 'name' => 'Springfield', 'slug' => 'springfield-b', 'is_active' => true]
        )->assertRedirect();

        $this->assertSame(2, City::where('name', 'Springfield')->count());
    }

    // ---- 12 city slug collision safe ------------------------------------------

    public function test_city_slug_collision_rejected(): void
    {
        $country = Country::factory()->create();
        City::factory()->create(['country_id' => $country->id, 'slug' => 'taken-slug']);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $country->id, 'name' => 'Another Town', 'slug' => 'taken-slug', 'is_active' => true]
        )->assertSessionHasErrors('slug');
    }

    // ---- 13 destination can belong to city --------------------------------

    public function test_destination_can_belong_to_city(): void
    {
        $city = City::factory()->create();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.destinations.store', absolute: false),
            ['name' => 'Old Town', 'slug' => 'old-town', 'city_id' => $city->id, 'is_active' => true]
        )->assertRedirect(route('admin.destinations.index', absolute: false));

        // Geography context is inherited from the city chain.
        $this->assertDatabaseHas('destinations', [
            'slug' => 'old-town',
            'city_id' => $city->id,
            'country_id' => $city->country_id,
        ]);
    }

    // ---- 14 destination without city allowed --------------------------------

    public function test_destination_can_exist_without_city(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.destinations.store', absolute: false),
            ['name' => 'Misty Highlands', 'slug' => 'misty-highlands', 'destination_type' => Destination::TYPE_REGION, 'is_active' => true]
        )->assertRedirect(route('admin.destinations.index', absolute: false));

        $this->assertDatabaseHas('destinations', ['slug' => 'misty-highlands', 'city_id' => null]);
    }

    // ---- 15 destination parent hierarchy -------------------------------------

    public function test_destination_parent_hierarchy_works(): void
    {
        $parent = Destination::factory()->withoutCity()->create(['name' => 'Greater Bay', 'slug' => 'greater-bay-test']);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.destinations.store', absolute: false),
            ['name' => 'Bay North', 'slug' => 'bay-north-test', 'parent_id' => $parent->id, 'is_active' => true]
        )->assertRedirect(route('admin.destinations.index', absolute: false));

        $this->assertDatabaseHas('destinations', ['slug' => 'bay-north-test', 'parent_id' => $parent->id]);
    }

    // ---- 16 self parent rejected ----------------------------------------------

    public function test_destination_self_parent_rejected(): void
    {
        $destination = Destination::factory()->withoutCity()->create();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.destinations.update', $destination, absolute: false),
            ['name' => $destination->name, 'slug' => $destination->slug, 'parent_id' => $destination->id, 'is_active' => true]
        )->assertSessionHasErrors('parent_id');
    }

    // ---- 17 destination cycle rejected ------------------------------------------

    public function test_destination_cycle_rejected(): void
    {
        $parent = Destination::factory()->withoutCity()->create();
        $child = Destination::factory()->withoutCity()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.destinations.update', $parent, absolute: false),
            ['name' => $parent->name, 'slug' => $parent->slug, 'parent_id' => $child->id, 'is_active' => true]
        )->assertSessionHasErrors('parent_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    // ---- 18 destination geography mismatch rejected ------------------------------

    public function test_destination_geography_mismatch_rejected(): void
    {
        $stateA = State::factory()->create();
        $stateB = State::factory()->create();
        $city = City::factory()->create(['country_id' => null, 'state_id' => $stateA->id]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.destinations.store', absolute: false),
            [
                'name' => 'Mismatch Bay', 'slug' => 'mismatch-bay',
                'city_id' => $city->id, 'state_id' => $stateB->id, 'is_active' => true,
            ]
        )->assertSessionHasErrors('city_id');

        $this->assertDatabaseMissing('destinations', ['slug' => 'mismatch-bay']);
    }

    // ---- 19 place belongs to valid geography ------------------------------------

    public function test_place_belongs_to_valid_geography(): void
    {
        $destination = Destination::factory()->create();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.places.store', absolute: false),
            ['name' => 'Harbour Light', 'slug' => 'harbour-light', 'destination_id' => $destination->id, 'is_active' => true]
        )->assertRedirect(route('admin.places.index', absolute: false));

        $this->assertDatabaseHas('places', ['slug' => 'harbour-light', 'destination_id' => $destination->id, 'is_active' => true]);
    }

    // ---- 20 property valid geography accepted ------------------------------------

    public function test_property_accepts_valid_country_state_city(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => $state->id]);

        $property = $this->createProperty([
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
        ]);

        $this->assertSame($city->id, (int) $property->city_id);
        $this->assertSame($country->id, (int) $property->country_id);
    }

    // ---- 21 property invalid hierarchy rejected ------------------------------------

    public function test_property_rejects_invalid_hierarchy(): void
    {
        $countryA = Country::factory()->create();
        $countryB = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $countryA->id, 'state_id' => null]);
        $foreignState = State::factory()->create(['country_id' => $countryB->id]);

        $this->expectException(ValidationException::class);

        $this->createProperty([
            'country_id' => $countryA->id,
            'state_id' => $foreignState->id,
            'city_id' => $city->id,
        ]);
    }

    // ---- 22 vendor cannot forge property geography ----------------------------------

    public function test_vendor_cannot_forge_property_geography(): void
    {
        $vendor = $this->makeVendor();
        $profile = $vendor->vendorProfile;
        $country = Country::factory()->create();
        $foreignCountry = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => null]);
        $foreignState = State::factory()->create(['country_id' => $foreignCountry->id]);

        $property = app(HotelPropertyService::class)->create(
            $this->propertyPayload(['city_id' => $city->id]),
            $this->makeAdmin(),
            $profile->id
        );

        $this->actingAs($vendor)->put(
            route('vendor.hotel.properties.update', $property, absolute: false),
            $this->propertyPayload([
                'city_id' => $city->id,
                'state_id' => $foreignState->id,
            ])
        )->assertSessionHasErrors('state_id');

        $this->assertNull($property->fresh()->state_id);
    }

    // ---- 23 property optional destination accepted ----------------------------------

    public function test_property_accepts_optional_destination(): void
    {
        $city = City::factory()->create();
        $destination = Destination::factory()->create([
            'country_id' => null, 'state_id' => null, 'city_id' => $city->id,
        ]);

        $property = $this->createProperty([
            'country_id' => $city->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'destination_id' => $destination->id,
        ]);

        $this->assertTrue($property->destination->is($destination));
    }

    // ---- 24 property foreign destination rejected ------------------------------------

    public function test_property_rejects_foreign_destination(): void
    {
        $cityA = City::factory()->create();
        $cityB = City::factory()->create();
        $foreignDestination = Destination::factory()->create([
            'country_id' => null, 'state_id' => null, 'city_id' => $cityB->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->createProperty([
            'country_id' => $cityA->country_id,
            'city_id' => $cityA->id,
            'destination_id' => $foreignDestination->id,
        ]);
    }

    // ---- 25 hotel published count per city --------------------------------------------

    public function test_hotel_published_count_per_city_correct(): void
    {
        $city = City::factory()->featured()->create();

        $this->createProperty(['city_id' => $city->id], $this->makeAdmin());

        // Publish directly through the service (server-authoritative).
        $published = $this->createProperty(
            ['city_id' => $city->id, 'name' => 'Published Stay', 'publish_directly' => true],
            $this->makeAdmin()
        );

        $this->assertSame(PropertyStatus::Published->value, $published->status);

        $cards = app(TravelLocationService::class)->featuredCities();
        $card = $cards->firstWhere('id', $city->id);

        $this->assertNotNull($card);
        $this->assertSame(1, $card['property_count']);
    }

    // ---- 26 unpublished excluded -------------------------------------------------------

    public function test_unpublished_hotel_excluded_from_city_count(): void
    {
        $city = City::factory()->featured()->create();

        $this->createProperty(['city_id' => $city->id, 'name' => 'Draft Stay']);

        $card = app(TravelLocationService::class)->featuredCities()->firstWhere('id', $city->id);

        $this->assertNotNull($card);
        $this->assertSame(0, $card['property_count']);
    }

    // ---- 27 tour count per destination --------------------------------------------

    public function test_tour_count_per_destination_correct(): void
    {
        $city = City::factory()->create();
        $destination = Destination::factory()->featured()->create([
            'country_id' => null, 'state_id' => null, 'city_id' => $city->id,
        ]);

        $visible = $this->createTour($city, 'Visible Bay Tour', 'visible-bay-tour', true);
        $hidden = $this->createTour($city, 'Hidden Bay Tour', 'hidden-bay-tour', false);
        $destination->tourPackages()->sync([$visible->id, $hidden->id]);

        $card = app(TravelLocationService::class)->featuredDestinations()->firstWhere('id', $destination->id);

        $this->assertNotNull($card);
        $this->assertSame(1, $card['tour_count']);
    }

    // ---- 28 inactive excluded from discovery ------------------------------------------

    public function test_inactive_records_excluded_from_public_discovery(): void
    {
        $city = City::factory()->featured()->inactive()->create(['name' => 'Ghost Town Searchable']);
        $destination = Destination::factory()->featured()->inactive()->create([
            'country_id' => null, 'state_id' => null, 'city_id' => null,
            'name' => 'Ghost Town Searchable',
        ]);

        $this->assertNull(app(TravelLocationService::class)->featuredCities()->firstWhere('id', $city->id));
        $this->assertNull(app(TravelLocationService::class)->featuredDestinations()->firstWhere('id', $destination->id));
        $this->assertEmpty(
            app(TravelLocationService::class)->search('Ghost Town Searchable')->where('id', $destination->id)->all()
        );
    }

    // ---- 29 featured city query ----------------------------------------------------

    public function test_featured_city_query_works(): void
    {
        $featured = City::factory()->featured()->create();
        $plain = City::factory()->create();

        $ids = app(TravelLocationService::class)->featuredCities(50)->pluck('id')->all();

        $this->assertContains($featured->id, $ids);
        $this->assertNotContains($plain->id, $ids);
    }

    // ---- 30 featured destination query ----------------------------------------------

    public function test_featured_destination_query_works(): void
    {
        $featured = Destination::factory()->featured()->create();
        $plain = Destination::factory()->create();

        $ids = app(TravelLocationService::class)->featuredDestinations(50)->pluck('id')->all();

        $this->assertContains($featured->id, $ids);
        $this->assertNotContains($plain->id, $ids);
    }

    // ---- 31 ordering works --------------------------------------------------------------

    public function test_discovery_ordering_works(): void
    {
        $second = City::factory()->featured()->create(['sort_order' => 20]);
        $first = City::factory()->featured()->create(['sort_order' => 5]);

        $ids = app(TravelLocationService::class)->featuredCities(50)->pluck('id')->all();

        $this->assertLessThan(
            array_search($second->id, $ids, true),
            array_search($first->id, $ids, true)
        );
    }

    // ---- 32 discovery payload compact/safe ----------------------------------------------

    public function test_discovery_payload_is_compact_and_safe(): void
    {
        $city = City::factory()->featured()->create();

        $card = app(TravelLocationService::class)->featuredCities()->firstWhere('id', $city->id);

        $this->assertSame(
            ['id', 'name', 'slug', 'image', 'subtitle', 'property_count', 'tour_count'],
            array_keys($card)
        );
        $this->assertArrayNotHasKey('vendor_profile_id', $card);
    }

    // ---- 33 dependent states scoped -------------------------------------------------------

    public function test_dependent_states_scoped_by_country(): void
    {
        $countryA = Country::factory()->create();
        $countryB = Country::factory()->create();
        $stateA = State::factory()->create(['country_id' => $countryA->id]);
        State::factory()->create(['country_id' => $countryB->id]);

        $response = $this->actingAs($this->makeAdmin())->getJson(
            route('admin.locations.lookup.states', ['country_id' => $countryA->id], absolute: false)
        );

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $stateA->id);
    }

    // ---- 34 dependent cities scoped ----------------------------------------------------------

    public function test_dependent_cities_scoped_by_country_and_state(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => $state->id]);
        City::factory()->create();

        $response = $this->actingAs($this->makeAdmin())->getJson(
            route('admin.locations.lookup.cities', ['country_id' => $country->id, 'state_id' => $state->id], absolute: false)
        );

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $city->id);
    }

    // ---- 35 dependent destinations scoped -------------------------------------------------------

    public function test_dependent_destinations_scoped_correctly(): void
    {
        $city = City::factory()->create();
        $destination = Destination::factory()->create([
            'country_id' => null, 'state_id' => null, 'city_id' => $city->id,
        ]);
        Destination::factory()->create();

        $response = $this->actingAs($this->makeAdmin())->getJson(
            route('admin.locations.lookup.destinations', ['city_id' => $city->id], absolute: false)
        );

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $destination->id);
    }

    // ---- 36 malformed ids safely rejected --------------------------------------------------------

    public function test_malformed_lookup_ids_return_empty_sets(): void
    {
        City::factory()->create();

        $this->actingAs($this->makeAdmin())->getJson(
            route('admin.locations.lookup.cities', ['state_id' => 'not-an-int'], absolute: false)
        )->assertOk()->assertExactJson([]);
    }

    // ---- 37 location writes audited ----------------------------------------------------------------

    public function test_location_writes_are_audited(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.countries.store', absolute: false),
            $this->countryPayload(['name' => 'Auditland', 'iso2' => 'AL'])
        )->assertRedirect();

        $this->assertTrue(
            ActivityLog::where('module', 'locations')->where('event', 'country.created')->exists()
        );
    }

    // ---- 38 public reads not audit-spammed ------------------------------------------------------------

    public function test_public_discovery_reads_are_not_audit_spammed(): void
    {
        City::factory()->featured()->create();

        $before = ActivityLog::count();

        app(TravelLocationService::class)->featuredCities();
        app(TravelLocationService::class)->featuredDestinations();
        app(TravelLocationService::class)->search('Test City');

        $this->assertSame($before, ActivityLog::count());
    }

    // ---- 39 module-independent geography ---------------------------------------------------------------

    public function test_geography_routes_work_with_hotels_disabled(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeAdmin())->get(route('admin.countries.index', absolute: false))->assertOk();
        $this->actingAs($this->makeAdmin())->getJson(
            route('admin.locations.lookup.cities', absolute: false)
        )->assertOk();
    }

    // ---- 40 hotel property forms keep working ---------------------------------------------------------------

    public function test_hotel_property_forms_continue_working(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.hotel.properties.create', absolute: false))->assertOk();

        $this->actingAs($this->makeVendor())
            ->get(route('vendor.hotel.properties.create', absolute: false))->assertOk();
    }

    // ---- 41 hotel room routes unaffected ------------------------------------------------------------------------

    public function test_hotel_room_routes_unaffected(): void
    {
        $property = Property::factory()->create();

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.room-types.index', $property, absolute: false)
        )->assertOk();
    }

    // ---- 42 hotel inventory unaffected -----------------------------------------------------------------------------

    public function test_hotel_inventory_unaffected(): void
    {
        $property = Property::factory()->create();
        $room = HotelRoomType::factory()->create(['property_id' => $property->id]);

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.inventory.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
                'start' => '2027-03-01', 'end' => '2027-03-31',
            ])
        )->assertOk();
    }

    // ---- 43 hotel pricing unaffected ---------------------------------------------------------------------------------

    public function test_hotel_pricing_unaffected(): void
    {
        $property = Property::factory()->create();
        $room = HotelRoomType::factory()->create(['property_id' => $property->id]);

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.rate-plans.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
            ])
        )->assertOk();
    }

    // ---- 44 tour destination relation functional --------------------------------------------------------------------------

    public function test_tour_destination_relation_remains_functional(): void
    {
        $city = City::factory()->create();
        $tour = $this->createTour($city, 'Three Bay Hopper', 'three-bay-hopper');
        $first = Destination::factory()->create(['name' => 'Hopper Bay One Unique']);
        $second = Destination::factory()->create(['name' => 'Hopper Bay Two Unique']);
        $place = Place::factory()->create(['destination_id' => $first->id]);

        $tour->destinations()->sync([$first->id, $second->id]);
        $tour->places()->sync([$place->id]);

        $this->assertSame(2, $tour->destinations()->count());
        $this->assertSame(1, $tour->places()->count());
        $this->get('/packages?destination='.$first->slug)->assertOk();
    }

    // ---- 45 place relation functional ------------------------------------------------------------------------------------------

    public function test_place_relation_remains_functional(): void
    {
        $city = City::factory()->create();
        $tour = $this->createTour($city, 'Lighthouse Run', 'lighthouse-run');
        $place = Place::factory()->create();

        $tour->places()->attach($place->id);

        $this->assertTrue($place->tourPackages()->whereKey($tour->id)->exists());
        $this->get('/places/'.$place->slug)->assertOk();
    }

    // ---- 46 taxi core unaffected ----------------------------------------------------------------------------------------------------

    public function test_taxi_routes_and_core_unaffected(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.taxi.bookings.index', absolute: false))->assertOk();

        $this->assertTrue(Schema::hasColumns('taxi_bookings', [
            'pickup_address', 'pickup_lat', 'pickup_lng', 'drop_address', 'drop_lat', 'drop_lng',
        ]));
        $this->assertFalse(Schema::hasColumn('taxi_bookings', 'pickup_city_id'));
    }

    // ---- 47 geography does not alter pricing currency ------------------------------------------------------------------------------

    public function test_geography_does_not_alter_pricing_currency(): void
    {
        $country = Country::factory()->create(['currency_code' => 'INR']);

        $property = $this->createProperty([
            'country_id' => $country->id,
            'currency' => 'USD',
        ]);

        $this->assertSame('USD', $property->currency);
        $this->assertSame($country->id, (int) $property->country_id);
    }

    // ---- 48 custom fields do not override geography ------------------------------------------------------------------------------------

    public function test_custom_fields_do_not_override_structured_geography(): void
    {
        $city = City::factory()->create();
        $property = $this->createProperty(['city_id' => $city->id]);

        $definition = HotelCustomFieldDefinition::factory()->create([
            'entity_type' => HotelCustomFieldDefinition::ENTITY_PROPERTY,
        ]);
        $service = app(HotelCustomFieldService::class);
        $service->saveValues('property', $property->id, $service->validateValues(
            'property',
            [$definition->id => 'Near the old harbour'],
            $property->property_type_id
        ));

        $this->assertSame($city->id, (int) $property->fresh()->city_id);
        $this->assertTrue(
            HotelCustomFieldValue::where('definition_id', $definition->id)
                ->where('entity_id', $property->id)
                ->exists()
        );
    }

    // ---- 49 demo seeder idempotent ---------------------------------------------------------------------------------------------------------

    public function test_hotel_demo_seeder_remains_idempotent(): void
    {
        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(3, Property::whereIn('slug', [
            'demo-harbour-hotel', 'demo-lakeside-homestay', 'demo-downtown-hostel',
        ])->count());

        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(3, Property::whereIn('slug', [
            'demo-harbour-hotel', 'demo-lakeside-homestay', 'demo-downtown-hostel',
        ])->count());
    }

    // ---- 50 geography factories scale ----------------------------------------------------------------------------------------------------------

    public function test_geography_factories_scale(): void
    {
        City::factory()->count(30)->create();
        Destination::factory()->count(20)->create();
        State::factory()->count(15)->create();

        // Destination factories build their own city (+state) each, so
        // totals include those relational rows — deterministically.
        $this->assertSame(50, City::count());
        $this->assertSame(20, Destination::count());
        $this->assertSame(65, State::count());
    }

    // ---- 51 no global city-name uniqueness ----------------------------------------------------------------------------------------------

    public function test_city_name_not_globally_unique(): void
    {
        $state = State::factory()->create();

        City::factory()->create(['name' => 'Riverton', 'slug' => 'riverton-one', 'country_id' => null, 'state_id' => $state->id]);
        City::factory()->create(['name' => 'Riverton', 'slug' => 'riverton-two', 'country_id' => null, 'state_id' => $state->id]);

        $this->assertSame(2, City::where('name', 'Riverton')->count());
    }

    // ---- 52 SEO metadata foundation ------------------------------------------------------------------------------------------------------------

    public function test_seo_metadata_generated_safely_for_city_and_destination(): void
    {
        $country = Country::factory()->create(['name' => 'Seoland']);
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => null, 'name' => 'Seoville']);
        $destination = Destination::factory()->withoutCity()->create([
            'name' => 'Seo Bay', 'meta_title' => 'Custom Seo Bay Title',
        ]);

        $service = app(TravelLocationService::class);
        $citySeo = $service->seoFor($city->fresh());
        $destinationSeo = $service->seoFor($destination);

        $this->assertStringContainsString('Seoville', $citySeo['title']);
        $this->assertStringContainsString('Seoville', (string) $citySeo['description']);
        $this->assertSame('Custom Seo Bay Title', $destinationSeo['title']);
        $this->assertSame('city', $citySeo['kind']);
        $this->assertSame('destination', $destinationSeo['kind']);
    }

    // ---- 53 public inactive destination hidden ------------------------------------------------------------------------------------------------------

    public function test_public_inactive_destination_hidden(): void
    {
        $destination = Destination::factory()->inactive()->create();

        $this->get('/destinations/'.$destination->slug)->assertNotFound();
        $this->get('/destinations/'.Destination::factory()->create()->slug)->assertOk();
    }

    // ---- 54 deactivate/reactivate without destruction --------------------------------------------------------------------------------------------------

    public function test_admin_can_deactivate_and_reactivate_without_destruction(): void
    {
        $admin = $this->makeAdmin();
        $destination = Destination::factory()->create();

        $this->actingAs($admin)->put(
            route('admin.destinations.update', $destination, absolute: false),
            ['name' => $destination->name, 'slug' => $destination->slug, 'is_active' => false]
        )->assertRedirect();

        $this->assertFalse($destination->fresh()->is_active);
        $this->assertDatabaseHas('destinations', ['id' => $destination->id]);

        $this->actingAs($admin)->put(
            route('admin.destinations.update', $destination, absolute: false),
            ['name' => $destination->name, 'slug' => $destination->slug, 'is_active' => true]
        )->assertRedirect();

        $this->assertTrue($destination->fresh()->is_active);
    }

    // ---- 55 no destructive legacy migration ---------------------------------------------------------------------------------------------------------------

    public function test_no_destructive_legacy_geography_migration(): void
    {
        foreach (['states', 'cities', 'destinations', 'places'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertTrue(Schema::hasColumns('cities', ['name', 'slug', 'state_id', 'country_id', 'is_active', 'is_featured']));
        $this->assertTrue(Schema::hasColumns('destinations', ['name', 'slug', 'city_id', 'country_id', 'is_active']));

        // Legacy-style row (pre-phase shape: no country context) still works.
        $state = State::create(['name' => 'Legacy Shire', 'slug' => 'legacy-shire']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Legacy Town', 'slug' => 'legacy-town']);

        $this->assertNull($city->country_id);
        $this->assertTrue($city->fresh()->is_active);
    }

    // ---- 56 existing destination ids preserved ---------------------------------------------------------------------------------------------------------------

    public function test_existing_destination_ids_preserved(): void
    {
        $destination = Destination::factory()->create();
        $id = $destination->id;
        $country = Country::factory()->create();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.destinations.update', $destination, absolute: false),
            ['name' => $destination->name, 'slug' => $destination->slug, 'country_id' => $country->id, 'is_active' => true]
        )->assertRedirect();

        $this->assertSame($id, $destination->fresh()->id);
        $this->assertSame($country->id, (int) $destination->fresh()->country_id);
    }

    // ---- 57 existing city ids preserved --------------------------------------------------------------------------------------------------------------------------

    public function test_existing_city_ids_preserved(): void
    {
        $city = City::factory()->create();
        $id = $city->id;
        $country = Country::factory()->create();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.cities.update', $city, absolute: false),
            ['name' => $city->name, 'slug' => $city->slug, 'country_id' => $country->id, 'is_active' => true]
        )->assertRedirect();

        $this->assertSame($id, $city->fresh()->id);
    }

    // ---- 58 existing state ids preserved ----------------------------------------------------------------------------------------------------------------------------

    public function test_existing_state_ids_preserved(): void
    {
        $state = State::factory()->create();
        $id = $state->id;
        $country = Country::factory()->create();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.states.update', $state, absolute: false),
            ['name' => $state->name, 'slug' => $state->slug, 'country_id' => $country->id, 'is_active' => true]
        )->assertRedirect();

        $this->assertSame($id, $state->fresh()->id);
    }

    // ---- 59 normalized labels ------------------------------------------------------------------------------------------------------------------------------------

    public function test_shared_service_returns_normalized_labels(): void
    {
        $country = Country::factory()->create(['name' => 'Labelia']);
        $state = State::factory()->create(['country_id' => $country->id, 'name' => 'Label State']);
        $city = City::factory()->create([
            'country_id' => $country->id, 'state_id' => $state->id, 'name' => 'Label City',
        ]);

        $this->assertSame('Label City, Label State, Labelia', LocationHierarchy::displayName($city->fresh()));

        $stateless = City::factory()->create(['country_id' => $country->id, 'state_id' => null, 'name' => 'Stateless Port']);

        $this->assertSame('Stateless Port, Labelia', LocationHierarchy::displayName($stateless->fresh()));
    }

    // ---- 60 search seam distinguishes types -----------------------------------------------------------------------------------------------------------------------

    public function test_search_seam_distinguishes_city_destination_place(): void
    {
        $city = City::factory()->create(['country_id' => null, 'state_id' => null, 'name' => 'Seam harborton unique']);
        $destination = Destination::factory()->withoutCity()->create(['name' => 'Seam harborton bay unique']);
        $place = Place::factory()->create(['destination_id' => $destination->id, 'name' => 'Seam harborton light unique']);

        $results = app(TravelLocationService::class)->search('Seam harborton');

        $this->assertSame(
            ['city', 'destination', 'place'],
            $results->pluck('type')->sort()->values()->all()
        );
        $this->assertContains($city->id, $results->where('type', 'city')->pluck('id')->all());
        $this->assertContains($destination->id, $results->where('type', 'destination')->pluck('id')->all());
        $this->assertContains($place->id, $results->where('type', 'place')->pluck('id')->all());
    }

    // ---- 61 no hardcoded india-only behavior -------------------------------------------------------------------------------------------------------------------------

    public function test_no_hardcoded_india_only_behavior(): void
    {
        $country = Country::factory()->create(['name' => 'Freedonia', 'iso2' => 'FD']);
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => null, 'name' => 'Libertyville']);

        $property = $this->createProperty(['country_id' => $country->id, 'city_id' => $city->id]);

        $this->assertSame($country->id, (int) $property->country_id);

        $this->createTour($city, 'Libertyville Day Out', 'libertyville-day-out', true);

        $response = $this->getJson('/search?q=Libertyville');

        $response->assertOk();
        $this->assertStringNotContainsString('North India', (string) $response->getContent());
    }

    // ---- 62 null state works ------------------------------------------------------------------------------------------------------------------------------------------

    public function test_null_state_works_for_countries_without_state_usage(): void
    {
        $country = Country::factory()->create(['name' => 'Isle Test']);

        $city = City::factory()->withoutState()->create(['country_id' => $country->id, 'name' => 'Isle Port']);

        $this->assertNull($city->state_id);
        $this->assertSame('Isle Port, Isle Test', LocationHierarchy::displayName($city->fresh()));
    }

    // ---- 63 property address independent -----------------------------------------------------------------------------------------------------------------------------------

    public function test_property_address_still_works_independently(): void
    {
        $property = $this->createProperty([
            'address_line_1' => '7 Harbour Road',
            'address_line_2' => 'Pier 4',
            'postal_code' => '90210',
        ]);

        $this->assertNull($property->city_id);
        $this->assertSame('7 Harbour Road', $property->address_line_1);
        $this->assertSame('90210', $property->postal_code);
    }

    // ---- 64 lat/lng optional and validated -------------------------------------------------------------------------------------------------------------------------------------

    public function test_lat_lng_optional_and_validated(): void
    {
        $country = Country::factory()->create();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $country->id, 'name' => 'Far North', 'slug' => 'far-north', 'latitude' => 200, 'is_active' => true]
        )->assertSessionHasErrors('latitude');

        $this->actingAs($this->makeAdmin())->post(
            route('admin.cities.store', absolute: false),
            ['country_id' => $country->id, 'name' => 'Far North', 'slug' => 'far-north', 'latitude' => 64.14, 'longitude' => -21.9, 'is_active' => true]
        )->assertRedirect();

        $this->assertDatabaseHas('cities', ['slug' => 'far-north']);
    }

    // ---- 65 navigation/permissions consistent -------------------------------------------------------------------------------------------------------------------------------------

    public function test_navigation_and_permissions_consistent(): void
    {
        foreach ([
            'locations.countries.view', 'locations.countries.manage',
            'locations.states.view', 'locations.states.manage',
            'locations.cities.view', 'locations.cities.manage',
            'locations.destinations.view', 'locations.destinations.manage',
            'locations.places.view', 'locations.places.manage',
        ] as $permission) {
            $this->assertTrue(StaffPermissions::isKnown($permission), "missing permission {$permission}");
        }

        $this->assertSame('locations.countries.view', EnsureStaffPermission::permissionForRoute('admin.countries.index'));
        $this->assertSame('locations.cities.manage', EnsureStaffPermission::permissionForRoute('admin.cities.store'));

        $groups = AdminNavigation::filteredFor($this->makeAdmin());
        $locations = collect($groups)->firstWhere('key', 'locations');

        $this->assertNotNull($locations);
        $this->assertEqualsCanonicalizing(
            ['Countries', 'States & Regions', 'Cities', 'Destinations', 'Places'],
            collect($locations['items'])->pluck('label')->all()
        );

        $this->assertTrue(Role::where('name', 'super-admin')->firstOrFail()->hasPermissionTo('locations.countries.manage'));
        $this->assertTrue(Role::where('name', 'administrator')->firstOrFail()->hasPermissionTo('locations.cities.view'));
        $this->assertTrue(Role::where('name', 'operations-manager')->firstOrFail()->hasPermissionTo('locations.destinations.manage'));
    }
}
