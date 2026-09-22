<?php

namespace Tests\Feature\Hotel;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\ActivityLog;
use App\Models\HotelCustomFieldDefinition;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\HotelRoomUnit;
use App\Models\Property;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelAvailabilityService;
use App\Services\HotelCustomFieldService;
use App\Support\AdminNavigation;
use App\Support\ModuleManager;
use Database\Seeders\HotelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HotelInventoryTest extends TestCase
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
        ], $overrides))->fresh();
    }

    protected function roomFor(Property $property, array $overrides = []): HotelRoomType
    {
        return HotelRoomType::factory()->create(array_merge([
            'property_id' => $property->id,
            'status' => RoomTypeStatus::Active->value,
            'inventory_mode' => HotelRoomType::INVENTORY_AGGREGATE,
            'total_units' => 10,
        ], $overrides))->fresh();
    }

    protected function unitFor(HotelRoomType $room, string $status = HotelRoomUnit::STATUS_ACTIVE): HotelRoomUnit
    {
        return HotelRoomUnit::factory()->create([
            'property_id' => $room->property_id,
            'room_type_id' => $room->id,
            'status' => $status,
        ]);
    }

    protected function availability(): HotelAvailabilityService
    {
        return app(HotelAvailabilityService::class);
    }

    protected function availabilityUrl(Property $property, array $query): string
    {
        return route('hotels.availability', $property->slug, absolute: false).'?'.http_build_query($query);
    }

    // ---- 1 module disabled blocks admin -------------------------------

    public function test_module_disabled_blocks_admin_inventory_route(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.inventory.index', absolute: false)
        )->assertNotFound();
    }

    // ---- 2 module disabled blocks vendor --------------------------------

    public function test_module_disabled_blocks_vendor_inventory_route(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeVendor())->get(
            route('vendor.hotel.inventory.index', absolute: false)
        )->assertNotFound();
    }

    // ---- 3 admin can access calendar ------------------------------------

    public function test_admin_can_access_inventory_calendar(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.inventory.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
                'start' => '2027-03-01', 'end' => '2027-03-31',
            ])
        )->assertOk();
    }

    // ---- 4 vendor own access --------------------------------------------

    public function test_vendor_can_access_own_inventory(): void
    {
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);
        $room = $this->roomFor($property);

        $response = $this->actingAs($vendor)->get(
            route('vendor.hotel.inventory.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
                'start' => '2027-03-01', 'end' => '2027-03-31',
            ])
        );

        $response->assertOk();
        $response->assertSee('rows', false);
    }

    // ---- 5 vendor foreign ignored ---------------------------------------

    public function test_vendor_cannot_access_foreign_inventory(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $foreignRoom = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        // Foreign room id is silently ignored: no calendar resolves.
        $this->actingAs($vendorB)->get(
            route('vendor.hotel.inventory.index', absolute: false).'?'.http_build_query([
                'room_type_id' => $foreignRoom->id, 'start' => '2027-03-01', 'end' => '2027-03-31',
            ])
        )->assertOk();

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 6 vendor update foreign blocked --------------------------------

    public function test_vendor_cannot_update_foreign_room_inventory(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $foreignRoom = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.inventory.store', absolute: false),
            ['room_type_id' => $foreignRoom->id, 'date' => '2027-03-10', 'blocked_units' => 2]
        )->assertNotFound();

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 7 aggregate structural capacity --------------------------------

    public function test_aggregate_structural_capacity_uses_total_units(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 12]);

        $this->assertSame(12, $this->availability()->structuralCapacity($room));
    }

    // ---- 8 units mode counts active -------------------------------------

    public function test_units_mode_capacity_counts_active_units(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['inventory_mode' => HotelRoomType::INVENTORY_UNITS]);
        $this->unitFor($room);
        $this->unitFor($room);

        $this->assertSame(2, $this->availability()->structuralCapacity($room));
    }

    // ---- 9 maintenance excluded -----------------------------------------

    public function test_maintenance_unit_excluded(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['inventory_mode' => HotelRoomType::INVENTORY_UNITS]);
        $this->unitFor($room);
        $this->unitFor($room, HotelRoomUnit::STATUS_MAINTENANCE);

        $this->assertSame(1, $this->availability()->structuralCapacity($room));
    }

    // ---- 10 out-of-service excluded -------------------------------------

    public function test_out_of_service_unit_excluded(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['inventory_mode' => HotelRoomType::INVENTORY_UNITS]);
        $this->unitFor($room);
        $this->unitFor($room, HotelRoomUnit::STATUS_OUT_OF_SERVICE);

        $this->assertSame(1, $this->availability()->structuralCapacity($room));
    }

    // ---- 11 inactive excluded -------------------------------------------

    public function test_inactive_unit_excluded(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['inventory_mode' => HotelRoomType::INVENTORY_UNITS]);
        $this->unitFor($room);
        $this->unitFor($room, HotelRoomUnit::STATUS_INACTIVE);

        $this->assertSame(1, $this->availability()->structuralCapacity($room));
    }

    // ---- 12 sparse default ----------------------------------------------

    public function test_no_daily_row_uses_structural_default(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);

        $night = $this->availability()->nightly($room, '2027-03-10');

        $this->assertSame(10, $night['available']);
        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 13 override applies --------------------------------------------

    public function test_capacity_override_applies(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10', 'capacity_override' => 8, 'blocked_units' => 1]
        )->assertRedirect();

        $night = $this->availability()->nightly($room, '2027-03-10');
        $this->assertSame(8, $night['override_capacity']);
        $this->assertSame(7, $night['available']);
    }

    // ---- 14 blocked reduces ---------------------------------------------

    public function test_blocked_units_reduce_availability(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10', 'blocked_units' => 3]
        )->assertRedirect();

        $this->assertSame(7, $this->availability()->nightly($room, '2027-03-10')['available']);
    }

    // ---- 15 stop-sell zero ----------------------------------------------

    public function test_stop_sell_returns_zero(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10', 'stop_sell' => true]
        )->assertRedirect();

        $night = $this->availability()->nightly($room, '2027-03-10');
        $this->assertTrue($night['stop_sell']);
        $this->assertSame(0, $night['available']);
    }

    // ---- 16 no negative -------------------------------------------------

    public function test_blocked_cannot_produce_negative_availability(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 10], $this->makeAdmin());

        $this->assertSame(0, $this->availability()->nightly($room, '2027-03-10')['available']);
    }

    // ---- 17 negative override invalid -----------------------------------

    public function test_override_cannot_produce_invalid_negative_capacity(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10', 'capacity_override' => -1]
        )->assertSessionHasErrors('capacity_override');

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 18 override above structural rejected --------------------------

    public function test_override_above_structural_capacity_rejected(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10', 'capacity_override' => 99]
        )->assertSessionHasErrors('capacity_override');

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 19 one row per room/date ---------------------------------------

    public function test_one_inventory_row_per_room_type_date(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->availability()->applyRange($room, '2027-03-10', '2027-03-10', ['blocked_units' => 1]);
        $this->availability()->applyRange($room, '2027-03-10', '2027-03-10', ['blocked_units' => 2]);

        $this->assertSame(1, HotelRoomInventory::where('hotel_room_type_id', $room->id)->count());
    }

    // ---- 20 duplicate update modifies -----------------------------------

    public function test_duplicate_date_update_modifies_existing_row(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 1]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10', 'blocked_units' => 4]
        )->assertRedirect();

        $this->assertSame(1, HotelRoomInventory::where('hotel_room_type_id', $room->id)->count());
        $this->assertSame(6, $this->availability()->nightly($room, '2027-03-10')['available']);
    }

    // ---- 21 default-equivalent removes row ------------------------------

    public function test_default_equivalent_update_removes_sparse_row(): void
    {
        $room = $this->roomFor($this->propertyFor());
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 2]);
        $this->assertSame(1, HotelRoomInventory::count());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10', 'blocked_units' => 0]
        )->assertRedirect();

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 22 clear restores default --------------------------------------

    public function test_clear_override_restores_structural_default(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);
        $this->availability()->setDay($room, '2027-03-10', ['stop_sell' => true]);

        $this->actingAs($this->makeAdmin())->delete(
            route('admin.hotel.inventory.clear', absolute: false),
            ['room_type_id' => $room->id, 'date' => '2027-03-10']
        )->assertRedirect();

        $this->assertSame(0, HotelRoomInventory::count());
        $this->assertSame(10, $this->availability()->nightly($room, '2027-03-10')['available']);
    }

    // ---- 23 range creates rows ------------------------------------------

    public function test_date_range_update_creates_expected_rows(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $summary = $this->availability()->applyRange($room, '2027-03-10', '2027-03-14', ['blocked_units' => 1]);

        $this->assertSame(5, $summary['dates_affected']);
        $this->assertSame(5, $summary['rows_stored']);
        $this->assertSame(5, HotelRoomInventory::where('hotel_room_type_id', $room->id)->count());
    }

    // ---- 24 range bounded ------------------------------------------------

    public function test_range_update_bounded_by_max_days(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-01-01', 'end_date' => '2028-06-01', 'stop_sell' => true]
        )->assertSessionHasErrors('end_date');

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 25 invalid range rejected ---------------------------------------

    public function test_invalid_date_range_rejected(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-03-14', 'end_date' => '2027-03-10', 'stop_sell' => true]
        )->assertSessionHasErrors('end_date');

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 26 checkout after checkin ----------------------------------------

    public function test_checkout_must_be_after_checkin(): void
    {
        $room = $this->roomFor($this->propertyFor());

        try {
            $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-10');
            $this->fail('Zero-night stay was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('check_out', $e->errors());
        }

        try {
            $this->availability()->checkRoomType($room, '2027-03-12', '2027-03-10');
            $this->fail('Reversed range was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('check_out', $e->errors());
        }
    }

    // ---- 27 exclusive checkout --------------------------------------------

    public function test_check_in_inclusive_check_out_exclusive(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 5]);
        $this->availability()->setDay($room, '2027-03-12', ['stop_sell' => true]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-12', 1);

        $this->assertTrue($check['available']);
        $this->assertSame(['2027-03-10', '2027-03-11'], array_column($check['nights'], 'date'));
    }

    // ---- 28 single night ---------------------------------------------------

    public function test_single_night_availability_uses_one_date(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 5]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-11', 1);

        $this->assertCount(1, $check['nights']);
        $this->assertSame(5, $check['min_available_rooms']);
        $this->assertTrue($check['available']);
    }

    // ---- 29 two nights -------------------------------------------------------

    public function test_two_night_stay_evaluates_both_nights(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 5]);
        $this->availability()->setDay($room, '2027-03-11', ['blocked_units' => 2]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-12', 1);

        $this->assertCount(2, $check['nights']);
        $this->assertSame(3, $check['min_available_rooms']);
        $this->assertTrue($check['available']);
    }

    // ---- 30 every night succeeds -----------------------------------------------

    public function test_requested_quantity_available_on_every_night_succeeds(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 5]);
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 3]);
        $this->availability()->setDay($room, '2027-03-11', ['blocked_units' => 2]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-12', 2);

        $this->assertTrue($check['available']);
        $this->assertSame(2, $check['min_available_rooms']);
    }

    // ---- 31 one weak night fails ---------------------------------------------------

    public function test_one_insufficient_night_makes_range_unavailable(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 5]);
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 3]);
        $this->availability()->setDay($room, '2027-03-11', ['blocked_units' => 2]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-12', 3);

        $this->assertFalse($check['available']);
        $this->assertSame(2, $check['min_available_rooms']);
    }

    // ---- 32 min available -----------------------------------------------------------------

    public function test_min_available_rooms_calculated_correctly(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 6]);
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 2]);
        $this->availability()->setDay($room, '2027-03-11', ['blocked_units' => 4]);
        $this->availability()->setDay($room, '2027-03-12', ['blocked_units' => 1]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-13', 1);

        $this->assertSame([4, 2, 5], array_column($check['nights'], 'available'));
        $this->assertSame(2, $check['min_available_rooms']);
    }

    // ---- 33 inactive room publicly unavailable -------------------------------------------------------

    public function test_inactive_room_type_unavailable_publicly(): void
    {
        $property = $this->propertyFor();
        $active = $this->roomFor($property, ['name' => 'Sellable Room Alpha']);
        $this->roomFor($property, ['name' => 'Hidden Room Beta', 'status' => RoomTypeStatus::Draft->value]);

        $response = $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'rooms' => 1,
        ]));

        $response->assertOk();
        $slugs = array_column($response->json('room_types'), 'slug');
        $this->assertContains($active->slug, $slugs);
        $this->assertNotContains('Hidden Room Beta', array_column($response->json('room_types'), 'name'));
    }

    // ---- 34 unpublished property -----------------------------------------------------------------------------

    public function test_unpublished_property_unavailable_publicly(): void
    {
        $property = $this->propertyFor(null, ['status' => PropertyStatus::Draft->value]);

        $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'rooms' => 1,
        ]))->assertNotFound();
    }

    // ---- 35 active published available -----------------------------------------------------------------------------------

    public function test_active_published_room_type_available(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['total_units' => 4]);

        $response = $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'rooms' => 2,
        ]));

        $response->assertOk();
        $this->assertTrue($response->json('available'));
    }

    // ---- 36 validates rooms ---------------------------------------------------------------------------------------------------------

    public function test_public_endpoint_validates_room_quantity(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property);

        $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'rooms' => 0,
        ]))->assertJsonValidationErrors('rooms');
    }

    // ---- 37 validates dates -------------------------------------------------------------------------------------------------------------------

    public function test_public_endpoint_validates_dates(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property);

        $this->getJson($this->availabilityUrl($property, [
            'check_in' => 'not-a-date', 'check_out' => '2027-03-12', 'rooms' => 1,
        ]))->assertJsonValidationErrors('check_in');
    }

    // ---- 38 max stay -------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_endpoint_respects_max_stay_nights(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property);

        $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-01', 'check_out' => '2027-04-05', 'rooms' => 1,
        ]))->assertJsonValidationErrors('check_out');
    }

    // ---- 39 no internal note -----------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_availability_excludes_internal_note(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 1, 'note' => 'NOTESECRET999']);

        $response = $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-11', 'rooms' => 1,
        ]));

        $response->assertOk();
        $this->assertStringNotContainsString('NOTESECRET999', $response->getContent());
    }

    // ---- 40 no unit identifiers -----------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_availability_excludes_physical_unit_identifiers(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, ['inventory_mode' => HotelRoomType::INVENTORY_UNITS]);
        $this->unitFor($room)->forceFill(['unit_name' => 'Villa Z9'])->save();

        $response = $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-11', 'rooms' => 1,
        ]));

        $response->assertOk();
        $this->assertStringNotContainsString('Villa Z9', $response->getContent());
    }

    // ---- 41 eligible rooms only -----------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_available_room_types_returns_only_eligible(): void
    {
        $property = $this->propertyFor();
        $open = $this->roomFor($property, ['name' => 'Open Room', 'total_units' => 5]);
        $closed = $this->roomFor($property, ['name' => 'Closed Room', 'total_units' => 5]);
        $this->roomFor($property, ['name' => 'Draft Room', 'status' => RoomTypeStatus::Draft->value]);
        $this->availability()->setDay($closed, '2027-03-10', ['stop_sell' => true]);

        $checks = collect($this->availability()->availableRoomTypes($property, '2027-03-10', '2027-03-12', 1))
            ->keyBy('room_type_id');

        $this->assertTrue($checks->get($open->id)['available']);
        $this->assertFalse($checks->get($closed->id)['available']);
        $this->assertCount(2, $checks);
    }

    // ---- 42 foreign room rejected -------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_type_from_foreign_property_rejected(): void
    {
        $propertyA = $this->propertyFor();
        $propertyB = $this->propertyFor();
        $this->roomFor($propertyA);
        $foreignRoom = $this->roomFor($propertyB);

        $this->getJson($this->availabilityUrl($propertyA, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'rooms' => 1,
            'room_type_id' => $foreignRoom->id,
        ]))->assertNotFound();
    }

    // ---- 43 admin bulk stop-sell -------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_bulk_stop_sell_works(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 6]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-12', 'stop_sell' => true]
        )->assertRedirect();

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-13', 1);
        $this->assertFalse($check['available']);
        $this->assertSame(0, $check['min_available_rooms']);
    }

    // ---- 44 vendor bulk stop-sell -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_bulk_stop_sell_works_own_room(): void
    {
        $vendor = $this->makeVendor();
        $room = $this->roomFor($this->propertyFor($vendor->vendorProfile), ['total_units' => 4]);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-11', 'stop_sell' => true]
        )->assertRedirect();

        $this->assertSame(2, HotelRoomInventory::where('hotel_room_type_id', $room->id)->count());
        $this->assertSame(0, $this->availability()->nightly($room, '2027-03-10')['available']);
    }

    // ---- 45 vendor bulk foreign rejected -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_bulk_operation_foreign_room_rejected(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $foreignRoom = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $foreignRoom->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-12', 'stop_sell' => true]
        )->assertNotFound();

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 46 admin bulk blocked -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_bulk_blocked_units_update_works(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 8]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-11', 'blocked_units' => 3]
        )->assertRedirect();

        $this->assertSame(5, $this->availability()->nightly($room, '2027-03-10')['available']);
        $this->assertSame(5, $this->availability()->nightly($room, '2027-03-11')['available']);
        $this->assertSame(8, $this->availability()->nightly($room, '2027-03-12')['available']);
    }

    // ---- 47 override range -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_capacity_override_range_works(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-11', 'capacity_override' => 4]
        )->assertRedirect();

        $this->assertSame(4, $this->availability()->nightly($room, '2027-03-10')['available']);
        $this->assertSame(10, $this->availability()->nightly($room, '2027-03-12')['available']);
    }

    // ---- 48 atomic failure -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_mixed_update_validation_fails_atomically(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 5]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-14', 'blocked_units' => 99]
        )->assertSessionHasErrors('blocked_units');

        $this->assertSame(0, HotelRoomInventory::where('hotel_room_type_id', $room->id)->count());
    }

    // ---- 49 total_units change -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_changing_aggregate_total_units_updates_default_availability(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);
        $this->assertSame(10, $this->availability()->nightly($room, '2027-03-10')['available']);

        $room->forceFill(['total_units' => 6])->save();

        $this->assertSame(6, $this->availability()->nightly($room, '2027-03-10')['available']);
        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 50 override preserved -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_explicit_override_preserved_after_total_units_change(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 10]);
        $this->availability()->setDay($room, '2027-03-10', ['capacity_override' => 8]);

        $room->forceFill(['total_units' => 6])->save();

        $row = HotelRoomInventory::where('hotel_room_type_id', $room->id)->firstOrFail();
        $this->assertSame(8, (int) $row->capacity_override);
        $this->assertSame(6, $this->availability()->nightly($room, '2027-03-10')['available']);
    }

    // ---- 51 unit status change -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unit_status_change_updates_unit_mode_availability(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['inventory_mode' => HotelRoomType::INVENTORY_UNITS]);
        $unit = $this->unitFor($room);
        $this->unitFor($room);
        $this->unitFor($room);
        $this->assertSame(3, $this->availability()->nightly($room, '2027-03-10')['available']);

        $unit->forceFill(['status' => HotelRoomUnit::STATUS_MAINTENANCE])->save();

        $this->assertSame(2, $this->availability()->nightly($room, '2027-03-10')['available']);
        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 52 units optional -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_physical_room_units_remain_optional(): void
    {
        $room = $this->roomFor($this->propertyFor(), [
            'inventory_mode' => HotelRoomType::INVENTORY_AGGREGATE, 'total_units' => 7,
        ]);

        $this->assertSame(0, $room->units()->count());
        $this->assertSame(7, $this->availability()->nightly($room, '2027-03-10')['available']);
    }

    // ---- 53 read no audit -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inventory_read_does_not_audit_spam(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $before = ActivityLog::count();

        $this->actingAs($admin)->get(
            route('admin.hotel.inventory.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
                'start' => '2027-03-01', 'end' => '2027-03-31',
            ])
        )->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }

    // ---- 54 write audited -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inventory_write_audited(): void
    {
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.bulk', absolute: false),
            ['room_type_id' => $room->id, 'start_date' => '2027-03-10', 'end_date' => '2027-03-11', 'blocked_units' => 1]
        )->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'hotels', 'event' => 'hotel_inventory.range_updated',
        ]);
    }

    // ---- 55 public read no audit -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_availability_read_does_not_audit(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property);
        $before = ActivityLog::count();

        $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-11', 'rooms' => 1,
        ]))->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }

    // ---- 56 custom fields isolated -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_custom_fields_do_not_influence_inventory(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, ['total_units' => 9]);
        $def = HotelCustomFieldDefinition::factory()->create([
            'entity_type' => 'room_type', 'field_type' => 'number', 'name' => 'Rooms Available CF',
        ]);
        app(HotelCustomFieldService::class)->saveValues(
            'room_type', $room->id,
            app(HotelCustomFieldService::class)->validateValues('room_type', [$def->id => '1'])
        );

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-12', 1);

        $this->assertTrue($check['available']);
        $this->assertSame(9, $check['min_available_rooms']);
    }

    // ---- 57 no pricing -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_no_pricing_fields_exist_in_inventory(): void
    {
        $columns = Schema::getColumnListing('hotel_room_inventories');
        $this->assertNotContains('price', $columns);
        $this->assertNotContains('rate', $columns);
        $this->assertNotContains('amount', $columns);

        $property = $this->propertyFor();
        $this->roomFor($property);

        $data = $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-11', 'rooms' => 1,
        ]))->assertOk()->json();

        $this->assertArrayNotHasKey('price', $data);
        $this->assertStringNotContainsString('price', strtolower(json_encode($data)));
    }

    // ---- 58 seeder idempotent -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_demo_seeder_inventory_data_idempotent(): void
    {
        $this->seed(HotelDemoSeeder::class);
        $demoRoomIds = HotelRoomType::where('slug', 'like', 'demo-%')->pluck('id')->all();
        $first = HotelRoomInventory::whereIn('hotel_room_type_id', $demoRoomIds)->count();
        $this->assertSame(3, $first);

        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(3, HotelRoomInventory::whereIn('hotel_room_type_id', $demoRoomIds)->count());
        $this->assertSame($first, HotelRoomInventory::count());
    }

    // ---- 59 factory scale -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inventory_factory_can_generate_many_dates(): void
    {
        $room = $this->roomFor($this->propertyFor());

        for ($i = 1; $i <= 40; $i++) {
            $date = $i <= 30 ? sprintf('2027-06-%02d', $i) : sprintf('2027-07-%02d', $i - 30);
            HotelRoomInventory::factory()->create([
                'hotel_room_type_id' => $room->id,
                'inventory_date' => $date,
            ]);
        }

        $this->assertSame(40, HotelRoomInventory::where('hotel_room_type_id', $room->id)->count());
    }

    // ---- 60 admin nav -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_navigation_exposes_inventory_when_enabled(): void
    {
        $groups = AdminNavigation::filteredFor($this->makeAdmin());
        $hotels = collect($groups)->firstWhere('key', 'hotels');

        $this->assertNotNull($hotels);
        $this->assertNotNull(collect($hotels['items'])->firstWhere('id', 'hotel-inventory'));
    }

    // ---- 61 vendor nav ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_navigation_exposes_inventory_when_enabled(): void
    {
        $vendor = $this->makeVendor();
        $this->propertyFor($vendor->vendorProfile);

        $response = $this->actingAs($vendor)->get(route('vendor.hotel.inventory.index', absolute: false));

        $response->assertOk();
        $response->assertSee('rows', false);
    }

    // ---- 62 nav hidden disabled -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_navigation_hidden_when_module_disabled(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $groups = AdminNavigation::filteredFor($this->makeAdmin());

        $this->assertNull(collect($groups)->firstWhere('key', 'hotels'));
    }

    // ---- 63 forged room rejected -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_malformed_forged_room_type_id_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => 'not-an-id', 'date' => '2027-03-10', 'blocked_units' => 1]
        )->assertSessionHasErrors('room_type_id');

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.inventory.store', absolute: false),
            ['room_type_id' => 999999, 'date' => '2027-03-10', 'blocked_units' => 1]
        )->assertSessionHasErrors('room_type_id');

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 64 ownership server-side -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_ownership_derived_server_side(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $ownProperty = $this->propertyFor($vendorB->vendorProfile);
        $foreignRoom = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        // A request-supplied own property_id cannot launder a foreign room.
        $this->actingAs($vendorB)->post(
            route('vendor.hotel.inventory.store', absolute: false),
            ['room_type_id' => $foreignRoom->id, 'property_id' => $ownProperty->id, 'date' => '2027-03-10', 'blocked_units' => 1]
        )->assertNotFound();

        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 65 clamp zero -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_availability_service_clamps_zero_safely(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 3]);
        $this->availability()->setDay($room, '2027-03-10', ['stop_sell' => true]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-11', 1);

        $this->assertFalse($check['available']);
        $this->assertSame(0, $check['min_available_rooms']);
        $this->assertGreaterThanOrEqual(0, min(array_column($check['nights'], 'available')));
    }

    // ---- 66 notes internal -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_notes_remain_internal(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 1, 'note' => 'Owner hold weekend']);

        // Staff calendar carries the note.
        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.inventory.index', absolute: false).'?'.http_build_query([
                'property_id' => $property->id, 'room_type_id' => $room->id,
                'start' => '2027-03-01', 'end' => '2027-03-31',
            ])
        )->assertOk()->assertSee('Owner hold weekend', false);

        // Public payload never does.
        $response = $this->getJson($this->availabilityUrl($property, [
            'check_in' => '2027-03-10', 'check_out' => '2027-03-11', 'rooms' => 1,
        ]));

        $response->assertOk();
        $this->assertStringNotContainsString('Owner hold weekend', $response->getContent());
    }

    // ---- 67 calendar bounded -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_calendar_query_limited_to_requested_date_range(): void
    {
        $room = $this->roomFor($this->propertyFor());
        $this->availability()->setDay($room, '2027-03-10', ['blocked_units' => 1]);
        $this->availability()->setDay($room, '2027-04-01', ['blocked_units' => 1]);

        $rows = $this->availability()->calendar($room, '2027-03-01', '2027-03-31');

        $this->assertCount(31, $rows);
        $this->assertContains('2027-03-10', array_column($rows, 'date'));
        $this->assertNotContains('2027-04-01', array_column($rows, 'date'));
    }

    // ---- 68 sparse -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_sparse_architecture_does_not_require_rows_for_normal_dates(): void
    {
        $room = $this->roomFor($this->propertyFor(), ['total_units' => 5]);

        $check = $this->availability()->checkRoomType($room, '2027-03-10', '2027-03-13', 2);

        $this->assertTrue($check['available']);
        $this->assertSame(0, HotelRoomInventory::count());
    }

    // ---- 69 12B.2 room page renders -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_public_page_still_renders(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, ['name' => 'Harbour View Double']);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()
            ->assertSee('Harbour View Double', false);
    }

    // ---- 70 12B.2.1 custom fields render -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_custom_fields_still_render_independently(): void
    {
        $property = $this->propertyFor();
        $def = HotelCustomFieldDefinition::factory()->create([
            'entity_type' => 'property', 'field_type' => 'text', 'name' => 'Arrival Note CF',
        ]);
        app(HotelCustomFieldService::class)->saveValues(
            'property', $property->id,
            app(HotelCustomFieldService::class)->validateValues('property', [$def->id => 'CFINV999'])
        );

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()
            ->assertSee('CFINV999', false);
    }
}
