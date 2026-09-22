<?php

namespace Tests\Feature\Hotel;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\ActivityLog;
use App\Models\HotelAmenity;
use App\Models\HotelBedType;
use App\Models\HotelCustomFieldDefinition;
use App\Models\HotelCustomFieldValue;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelCustomFieldService;
use App\Support\ModuleManager;
use Database\Seeders\HotelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HotelCustomFieldTest extends TestCase
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

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'property_type_id' => PropertyType::factory()->create()->id,
            'name' => 'Grand Test Hotel',
            'address_line_1' => '12 Test Street',
            'country_code' => 'US',
        ], $overrides);
    }

    protected function roomPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Deluxe King Room',
            'max_adults' => 2,
            'max_children' => 1,
            'max_occupancy' => 3,
            'inventory_mode' => HotelRoomType::INVENTORY_AGGREGATE,
            'total_units' => 5,
        ], $overrides);
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
        ], $overrides))->fresh();
    }

    protected function makeDef(string $entity, string $type, array $overrides = []): HotelCustomFieldDefinition
    {
        return HotelCustomFieldDefinition::factory()->create(array_merge([
            'entity_type' => $entity,
            'field_type' => $type,
        ], $overrides));
    }

    protected function makeSelectDef(string $entity = 'property'): HotelCustomFieldDefinition
    {
        return $this->makeDef($entity, HotelCustomFieldDefinition::TYPE_SELECT, [
            'options' => [
                ['value' => 'sea-view', 'label' => 'Sea View'],
                ['value' => 'garden-view', 'label' => 'Garden View'],
            ],
        ]);
    }

    protected function makeMultiDef(string $entity = 'property'): HotelCustomFieldDefinition
    {
        return $this->makeDef($entity, HotelCustomFieldDefinition::TYPE_MULTISELECT, [
            'options' => [
                ['value' => 'english', 'label' => 'English'],
                ['value' => 'hindi', 'label' => 'Hindi'],
            ],
        ]);
    }

    protected function fields(): HotelCustomFieldService
    {
        return app(HotelCustomFieldService::class);
    }

    // ---- 1 admin can access custom fields -----------------------------

    public function test_admin_can_access_custom_fields(): void
    {
        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.custom-fields.index', absolute: false)
        )->assertOk();
    }

    // ---- 2 unauthorized staff blocked -----------------------------------

    public function test_unauthorized_staff_blocked(): void
    {
        $this->actingAs($this->makeVendor())->get(
            route('admin.hotel.custom-fields.index', absolute: false)
        )->assertForbidden();
    }

    // ---- 3 vendor cannot manage definitions -----------------------------

    public function test_vendor_cannot_manage_definitions(): void
    {
        $this->actingAs($this->makeVendor())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'property', 'name' => 'Sneaky Field', 'field_type' => 'text']
        )->assertForbidden();

        $this->assertDatabaseMissing('hotel_custom_field_definitions', ['name' => 'Sneaky Field']);
    }

    // ---- 4 property definition create -----------------------------------

    public function test_property_definition_create(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'property', 'name' => 'Nearest Airport', 'field_type' => 'text']
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_definitions', [
            'entity_type' => 'property', 'name' => 'Nearest Airport', 'key' => 'nearest_airport',
        ]);
    }

    // ---- 5 room definition create ---------------------------------------

    public function test_room_definition_create(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'room_type', 'name' => 'View Type', 'field_type' => 'text']
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_definitions', [
            'entity_type' => 'room_type', 'name' => 'View Type', 'key' => 'view_type',
        ]);
    }

    // ---- 6 unsupported entity rejected ----------------------------------

    public function test_unsupported_entity_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'tour', 'name' => 'Nope', 'field_type' => 'text']
        )->assertSessionHasErrors('entity_type');

        $this->assertDatabaseMissing('hotel_custom_field_definitions', ['name' => 'Nope']);
    }

    // ---- 7 text works ----------------------------------------------------

    public function test_text_works(): void
    {
        $def = $this->makeDef('property', 'text');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => '12 km']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'entity_type' => 'property',
            'entity_id' => $property->id, 'value' => '12 km',
        ]);
    }

    // ---- 8 textarea works -------------------------------------------------

    public function test_textarea_works(): void
    {
        $def = $this->makeDef('property', 'textarea');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'Quiet courtyard facing the temple.']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => 'Quiet courtyard facing the temple.',
        ]);
    }

    // ---- 9 number validates ------------------------------------------------

    public function test_number_validates(): void
    {
        $def = $this->makeDef('property', 'number');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'not-a-number']])
        )->assertSessionHasErrors('custom_fields.'.$def->id);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => '42']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => '42',
        ]);
    }

    // ---- 10 boolean normalizes ----------------------------------------------

    public function test_boolean_normalizes(): void
    {
        $def = $this->makeDef('property', 'boolean');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => true]])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => '1',
        ]);
        $this->assertSame('Yes', $this->fields()->publicValues('property', $property->id)[0]['fields'][0]['value']);
    }

    // ---- 11 date validates ---------------------------------------------------

    public function test_date_validates(): void
    {
        $def = $this->makeDef('property', 'date');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'not-a-date']])
        )->assertSessionHasErrors('custom_fields.'.$def->id);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => '2026-12-25']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => '2026-12-25',
        ]);
    }

    // ---- 12 url validates -----------------------------------------------------

    public function test_url_validates(): void
    {
        $def = $this->makeDef('property', 'url');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'not a url']])
        )->assertSessionHasErrors('custom_fields.'.$def->id);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'https://example.com/shuttle']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => 'https://example.com/shuttle',
        ]);
    }

    // ---- 13 select configured option accepted ----------------------------------

    public function test_select_configured_option_accepted(): void
    {
        $def = $this->makeSelectDef();
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'sea-view']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => 'sea-view',
        ]);
    }

    // ---- 14 invalid select rejected ---------------------------------------------

    public function test_invalid_select_rejected(): void
    {
        $def = $this->makeSelectDef();
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'moon-view']])
        )->assertSessionHasErrors('custom_fields.'.$def->id);

        $this->assertDatabaseMissing('hotel_custom_field_values', ['definition_id' => $def->id]);
    }

    // ---- 15 multiselect accepted --------------------------------------------------

    public function test_multiselect_accepted(): void
    {
        $def = $this->makeMultiDef();
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => ['english', 'hindi']]])
        )->assertRedirect();

        $value = HotelCustomFieldValue::where('definition_id', $def->id)->firstOrFail();
        $this->assertSame(['english', 'hindi'], $value->typed());
        $this->assertSame('English, Hindi', $value->display());
    }

    // ---- 16 invalid multiselect rejected --------------------------------------------

    public function test_invalid_multiselect_rejected(): void
    {
        $def = $this->makeMultiDef();
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => ['english', 'klingon']]])
        )->assertSessionHasErrors('custom_fields.'.$def->id);

        $this->assertDatabaseMissing('hotel_custom_field_values', ['definition_id' => $def->id]);
    }

    // ---- 17 stable key generation -----------------------------------------------------

    public function test_stable_key_generation(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'property', 'name' => 'Nearest Airport!', 'field_type' => 'text']
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_definitions', [
            'entity_type' => 'property', 'key' => 'nearest_airport',
        ]);
    }

    // ---- 18 duplicate key handling -------------------------------------------------------

    public function test_duplicate_key_handling(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'property', 'name' => 'Nearest Airport', 'field_type' => 'text']
        )->assertRedirect();
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'property', 'name' => 'Nearest Airport', 'field_type' => 'text']
        )->assertRedirect();

        $keys = HotelCustomFieldDefinition::where('entity_type', 'property')
            ->where('name', 'Nearest Airport')->pluck('key')->all();
        $this->assertCount(2, $keys);
        $this->assertCount(2, array_unique($keys));
    }

    // ---- 19 required applicable enforced ----------------------------------------------------

    public function test_required_applicable_enforced(): void
    {
        $def = $this->makeDef('property', 'text', ['is_required' => true]);
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name])
        )->assertSessionHasErrors('custom_fields.'.$def->id);
    }

    // ---- 20 optional may be empty --------------------------------------------------------------

    public function test_optional_may_be_empty(): void
    {
        $this->makeDef('property', 'text');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name])
        )->assertRedirect();
    }

    // ---- 21 inactive not required -------------------------------------------------------------------

    public function test_inactive_not_required(): void
    {
        $def = $this->makeDef('property', 'text', ['is_required' => true, 'is_active' => false]);
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name])
        )->assertRedirect();
    }

    // ---- 22 property-type applicability works -------------------------------------------------------------

    public function test_property_type_applicability_works(): void
    {
        $typeA = PropertyType::factory()->create();
        $typeB = PropertyType::factory()->create();
        $def = $this->makeDef('property', 'text');
        $def->propertyTypes()->attach($typeA->id);

        $this->assertTrue($this->fields()->applicableDefinitions('property', $typeA->id)->contains('id', $def->id));
        $this->assertFalse($this->fields()->applicableDefinitions('property', $typeB->id)->contains('id', $def->id));
        $this->assertFalse($this->fields()->applicableDefinitions('property', null)->contains('id', $def->id));
    }

    // ---- 23 non-applicable property excludes field ----------------------------------------------------------------

    public function test_non_applicable_property_excludes_field(): void
    {
        $typeA = PropertyType::factory()->create();
        $typeB = PropertyType::factory()->create();
        $def = $this->makeDef('property', 'text');
        $def->propertyTypes()->attach($typeA->id);
        $property = $this->propertyFor(null, ['property_type_id' => $typeB->id]);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload([
                'property_type_id' => $typeB->id, 'name' => $property->name,
                'custom_fields' => [$def->id => 'sneaky'],
            ])
        )->assertSessionHasErrors('custom_fields');
    }

    // ---- 24 admin property value save -------------------------------------------------------------------------------

    public function test_admin_property_value_save(): void
    {
        $def = $this->makeDef('property', 'text');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'Admin value']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'entity_type' => 'property',
            'entity_id' => $property->id, 'value' => 'Admin value',
        ]);
    }

    // ---- 25 vendor own property value save -------------------------------------------------------------------------------

    public function test_vendor_own_property_value_save(): void
    {
        $vendor = $this->makeVendor();
        $def = $this->makeDef('property', 'text');
        $property = $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->put(
            route('vendor.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'Vendor value']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'entity_id' => $property->id, 'value' => 'Vendor value',
        ]);
    }

    // ---- 26 foreign property blocked ------------------------------------------------------------------------------------------

    public function test_foreign_property_blocked(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $def = $this->makeDef('property', 'text');
        $property = $this->propertyFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)->put(
            route('vendor.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'Intruder']])
        )->assertNotFound();

        $this->assertDatabaseMissing('hotel_custom_field_values', ['value' => 'Intruder']);
    }

    // ---- 27 forged definition blocked ------------------------------------------------------------------------------------------------

    public function test_forged_definition_blocked(): void
    {
        $vendor = $this->makeVendor();
        $roomDef = $this->makeDef('room_type', 'text');
        $property = $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->put(
            route('vendor.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$roomDef->id => 'Forged']])
        )->assertSessionHasErrors('custom_fields');

        $this->assertDatabaseMissing('hotel_custom_field_values', ['value' => 'Forged']);
    }

    // ---- 28 room value save ----------------------------------------------------------------------------------------------------------------

    public function test_room_value_save(): void
    {
        $def = $this->makeDef('room_type', 'text');
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['custom_fields' => [$def->id => 'Sea facing']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'entity_type' => 'room_type',
            'entity_id' => $room->id, 'value' => 'Sea facing',
        ]);
    }

    // ---- 29 vendor own room value save ----------------------------------------------------------------------------------------------------------------

    public function test_vendor_own_room_value_save(): void
    {
        $vendor = $this->makeVendor();
        $def = $this->makeDef('room_type', 'text');
        $property = $this->propertyFor($vendor->vendorProfile);
        $room = $this->roomFor($property);

        $this->actingAs($vendor)->put(
            route('vendor.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['custom_fields' => [$def->id => 'Vendor room note']])
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'entity_id' => $room->id, 'value' => 'Vendor room note',
        ]);
    }

    // ---- 30 foreign room blocked --------------------------------------------------------------------------------------------------------------------------------

    public function test_foreign_room_blocked(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $def = $this->makeDef('room_type', 'text');
        $room = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        $this->actingAs($vendorB)->put(
            route('vendor.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['custom_fields' => [$def->id => 'Intruder']])
        )->assertNotFound();

        $this->assertDatabaseMissing('hotel_custom_field_values', ['value' => 'Intruder']);
    }

    // ---- 31 property/room value isolation ------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_room_value_isolation(): void
    {
        $propertyDef = $this->makeDef('property', 'text');
        $roomDef = $this->makeDef('room_type', 'text');
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        // Room definition id is unknown on the property form.
        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$roomDef->id => 'Cross']])
        )->assertSessionHasErrors('custom_fields');

        // Property definition id is unknown on the room form.
        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['custom_fields' => [$propertyDef->id => 'Cross']])
        )->assertSessionHasErrors('custom_fields');

        $this->assertDatabaseMissing('hotel_custom_field_values', ['value' => 'Cross']);
    }

    // ---- 32 one value per entity+definition ----------------------------------------------------------------------------------------------------------------------------------------

    public function test_one_value_per_entity_definition(): void
    {
        $def = $this->makeDef('property', 'text');
        $property = $this->propertyFor();

        foreach (['First', 'Second'] as $value) {
            $this->actingAs($this->makeAdmin())->put(
                route('admin.hotel.properties.update', $property, absolute: false),
                $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => $value]])
            )->assertRedirect();
        }

        $this->assertSame(1, HotelCustomFieldValue::where('definition_id', $def->id)
            ->where('entity_type', 'property')->where('entity_id', $property->id)->count());
        $this->assertSame('Second', HotelCustomFieldValue::where('definition_id', $def->id)->firstOrFail()->value);
    }

    // ---- 33 update does not duplicate ------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_update_does_not_duplicate(): void
    {
        $def = $this->makeDef('room_type', 'text');
        $room = $this->roomFor($this->propertyFor());

        $this->fields()->saveValues('room_type', $room->id, [$def->id => 'Original']);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['custom_fields' => [$def->id => 'Revised']])
        )->assertRedirect();

        $this->assertSame(1, HotelCustomFieldValue::where('definition_id', $def->id)->count());
        $this->assertSame('Revised', HotelCustomFieldValue::where('definition_id', $def->id)->firstOrFail()->value);
    }

    // ---- 34 deactivation preserves data ----------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_deactivation_preserves_data(): void
    {
        $def = $this->makeDef('property', 'text');
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'Keep me']);

        $this->actingAs($this->makeAdmin())->patch(
            route('admin.hotel.custom-fields.toggle', $def, absolute: false)
        )->assertRedirect();

        $this->assertFalse($def->fresh()->is_active);
        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'entity_id' => $property->id, 'value' => 'Keep me',
        ]);
    }

    // ---- 35 reactivation restores value ----------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_reactivation_restores_value(): void
    {
        $def = $this->makeDef('property', 'text', ['is_active' => false]);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'Restored']);

        $this->assertSame([], $this->fields()->publicValues('property', $property->id));

        $this->actingAs($this->makeAdmin())->patch(
            route('admin.hotel.custom-fields.toggle', $def, absolute: false)
        )->assertRedirect();

        $public = $this->fields()->publicValues('property', $property->id);
        $this->assertSame('Restored', $public[0]['fields'][0]['value']);
    }

    // ---- 36 inactive hidden publicly ----------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inactive_hidden_publicly(): void
    {
        $def = $this->makeDef('property', 'text', ['is_active' => false]);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'CFHIDDEN999']);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertDontSee('CFHIDDEN999');
    }

    // ---- 37 show_on_frontend=false hidden ----------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_frontend_hidden_definition_not_public(): void
    {
        $def = $this->makeDef('property', 'text', ['show_on_frontend' => false]);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'CFPRIVATE999']);

        $this->assertSame([], $this->fields()->publicValues('property', $property->id));
        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertDontSee('CFPRIVATE999');
    }

    // ---- 38 property visible field shown --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_visible_field_shown(): void
    {
        $def = $this->makeDef('property', 'text', ['name' => 'Nearest Airport']);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'CFPUBLIC999']);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertSee('CFPUBLIC999')->assertSee('Nearest Airport');
    }

    // ---- 39 room visible field shown ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_visible_field_shown(): void
    {
        $def = $this->makeDef('room_type', 'text', ['name' => 'View Type']);
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $this->fields()->saveValues('room_type', $room->id, [$def->id => 'CFROOM999']);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertSee('CFROOM999')->assertSee('View Type');
    }

    // ---- 40 empty omitted --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_empty_fields_omitted(): void
    {
        $this->makeDef('property', 'text', ['name' => 'CFEMPTYLABEL999']);
        $property = $this->propertyFor();

        $this->assertSame([], $this->fields()->publicValues('property', $property->id));
        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertDontSee('CFEMPTYLABEL999');
    }

    // ---- 41 groups ordered ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_groups_ordered(): void
    {
        $zulu = $this->makeDef('property', 'text', ['name' => 'Zulu Field', 'group_name' => 'Zulu Group', 'sort_order' => 1]);
        $alpha = $this->makeDef('property', 'text', ['name' => 'Alpha Field', 'group_name' => 'Alpha Group', 'sort_order' => 2]);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$zulu->id => 'z', $alpha->id => 'a']);

        $groups = array_column($this->fields()->publicValues('property', $property->id), 'group');
        $this->assertSame(['Zulu Group', 'Alpha Group'], $groups);
    }

    // ---- 42 fields ordered ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_fields_ordered(): void
    {
        $second = $this->makeDef('property', 'text', ['name' => 'Second Field', 'group_name' => 'Same Group', 'sort_order' => 5]);
        $first = $this->makeDef('property', 'text', ['name' => 'First Field', 'group_name' => 'Same Group', 'sort_order' => 1]);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$second->id => 's', $first->id => 'f']);

        $labels = array_column($this->fields()->publicValues('property', $property->id)[0]['fields'], 'label');
        $this->assertSame(['First Field', 'Second Field'], $labels);
    }

    // ---- 43 multiselect labels rendered ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_multiselect_labels_rendered(): void
    {
        $def = $this->makeMultiDef();
        $property = $this->propertyFor();
        $this->fields()->saveValues(
            'property', $property->id,
            $this->fields()->validateValues('property', [$def->id => ['english', 'hindi']])
        );

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertSee('English, Hindi');
    }

    // ---- 44 boolean rendered cleanly --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_boolean_rendered_cleanly(): void
    {
        $def = $this->makeDef('property', 'boolean', ['name' => 'EV Charging']);
        $property = $this->propertyFor();
        $this->fields()->saveValues(
            'property', $property->id,
            $this->fields()->validateValues('property', [$def->id => true])
        );

        $public = $this->fields()->publicValues('property', $property->id);
        $this->assertSame('Yes', $public[0]['fields'][0]['value']);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertSee('EV Charging');
    }

    // ---- 45 javascript url rejected ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_javascript_url_rejected(): void
    {
        $def = $this->makeDef('property', 'url');
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [$def->id => 'javascript:alert(1)']])
        )->assertSessionHasErrors('custom_fields.'.$def->id);

        $this->assertDatabaseMissing('hotel_custom_field_values', ['definition_id' => $def->id]);
    }

    // ---- 46 script content safe ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_script_content_safe(): void
    {
        $def = $this->makeDef('property', 'text');
        $property = $this->propertyFor();
        $this->fields()->saveValues(
            'property', $property->id,
            $this->fields()->validateValues('property', [$def->id => 'Hello<script>alert(1)</script>World'])
        );

        $stored = HotelCustomFieldValue::where('definition_id', $def->id)->firstOrFail()->value;
        $this->assertStringNotContainsString('<script>', $stored);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertDontSee('<script>alert');
    }

    // ---- 47 validation/config not exposed --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_validation_config_not_exposed(): void
    {
        $def = $this->makeDef('property', 'text', ['is_required' => true]);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'Visible']);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertDontSee('is_required')->assertDontSee('show_on_frontend');
    }

    // ---- 48 hidden value absent from props ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_hidden_value_absent_from_props(): void
    {
        $def = $this->makeDef('property', 'text', ['show_on_frontend' => false]);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'CFABSENT999']);

        $this->get(route('hotels.show', $property->slug, absolute: false))
            ->assertOk()->assertDontSee('CFABSENT999');
    }

    // ---- 49 property form section absent if none ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_form_section_absent_if_none(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.properties.edit', $property, absolute: false)
        )->assertOk()->assertDontSee('Additional Information');
    }

    // ---- 50 room form section absent if none --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_form_section_absent_if_none(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.room-types.create', $property, absolute: false)
        )->assertOk()->assertDontSee('Additional Room Details');
    }

    // ---- 51 definition writes audited ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_definition_writes_audited(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            ['entity_type' => 'property', 'name' => 'Audited Field', 'field_type' => 'text']
        )->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'hotels', 'event' => 'hotel_custom_field_definition.created',
        ]);
    }

    // ---- 52 public reads not audit-spammed --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_reads_not_audit_spammed(): void
    {
        $property = $this->propertyFor();
        $before = ActivityLog::count();

        $this->get(route('hotels.show', $property->slug, absolute: false))->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }

    // ---- 53 module disabled blocks management ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_module_disabled_blocks_management(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.custom-fields.index', absolute: false)
        )->assertNotFound();
    }

    // ---- 54 module-disabled hotel public remains safe --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_module_disabled_hotel_public_remains_safe(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);
        $property = $this->propertyFor();

        $this->get(route('hotels.index', absolute: false))->assertNotFound();
        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
    }

    // ---- 55 demo seeder idempotent ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_demo_seeder_idempotent(): void
    {
        $this->seed(HotelDemoSeeder::class);
        $keys = ['nearest_airport', 'languages_spoken', 'ev_charging', 'view_type', 'bathroom_type'];
        $this->assertSame(5, HotelCustomFieldDefinition::whereIn('key', $keys)->count());
        $values = HotelCustomFieldValue::count();

        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(5, HotelCustomFieldDefinition::whereIn('key', $keys)->count());
        $this->assertSame($values, HotelCustomFieldValue::count());
    }

    // ---- 56 factories scale --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_factories_scale(): void
    {
        HotelCustomFieldDefinition::factory()->count(25)->create();
        $this->assertSame(25, HotelCustomFieldDefinition::count());

        HotelCustomFieldValue::factory()->count(10)->create();
        $this->assertSame(10, HotelCustomFieldValue::count());
    }

    // ---- 57 custom fields do not affect occupancy ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_custom_fields_do_not_affect_occupancy(): void
    {
        $def = $this->makeDef('room_type', 'number', ['name' => 'Maximum Guests']);
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['custom_fields' => [$def->id => '99']])
        )->assertRedirect();

        $this->assertSame(3, $room->fresh()->max_occupancy);
        $this->assertSame(2, $room->fresh()->max_adults);
    }

    // ---- 58 custom fields do not affect inventory mode ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_custom_fields_do_not_affect_inventory_mode(): void
    {
        $def = $this->makeDef('room_type', 'text', ['name' => 'Inventory Note']);
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['custom_fields' => [$def->id => 'units']])
        )->assertRedirect();

        $this->assertSame(HotelRoomType::INVENTORY_AGGREGATE, $room->fresh()->inventory_mode);
        $this->assertSame(5, $room->fresh()->total_units);
    }

    // ---- 59 no pricing fields introduced --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_no_pricing_fields_introduced(): void
    {
        foreach (['hotel_custom_field_definitions', 'hotel_custom_field_values'] as $table) {
            $columns = Schema::getColumnListing($table);
            $this->assertNotContains('price', $columns);
            $this->assertNotContains('rate', $columns);
            $this->assertNotContains('availability', $columns);
        }
    }

    // ---- 60 property amenities regression ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_amenities_regression(): void
    {
        $def = $this->makeDef('property', 'text');
        $amenity = HotelAmenity::factory()->create(['is_active' => true]);
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload([
                'name' => $property->name, 'amenity_ids' => [$amenity->id],
                'custom_fields' => [$def->id => 'With amenities'],
            ])
        )->assertRedirect();

        $this->assertTrue($property->fresh()->amenities()->whereKey($amenity->id)->exists());
        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => 'With amenities',
        ]);
    }

    // ---- 61 room bed/amenity regression ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_bed_amenity_regression(): void
    {
        $def = $this->makeDef('room_type', 'text');
        $bed = HotelBedType::factory()->create(['is_active' => true]);
        $amenity = HotelAmenity::factory()->create(['is_active' => true]);
        $amenity->forceFill(['scope' => 'room'])->save();
        $room = $this->roomFor($this->propertyFor());

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload([
                'beds' => [['bed_type_id' => $bed->id, 'quantity' => 2]],
                'amenity_ids' => [$amenity->id],
                'custom_fields' => [$def->id => 'With beds'],
            ])
        )->assertRedirect();

        $fresh = $room->fresh();
        $this->assertSame(2, (int) $fresh->bedTypes()->firstOrFail()->pivot->quantity);
        $this->assertTrue($fresh->amenities()->whereKey($amenity->id)->exists());
        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'value' => 'With beds',
        ]);
    }

    // ---- 62 label change preserves value --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_label_change_preserves_value(): void
    {
        $def = $this->makeDef('property', 'text', ['name' => 'Old Label']);
        $property = $this->propertyFor();
        $this->fields()->saveValues('property', $property->id, [$def->id => 'Kept']);

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.custom-fields.update', $def, absolute: false),
            ['entity_type' => 'property', 'name' => 'New Label', 'key' => $def->key, 'field_type' => 'text']
        )->assertRedirect();

        $this->assertSame('New Label', $def->fresh()->name);
        $this->assertDatabaseHas('hotel_custom_field_values', [
            'definition_id' => $def->id, 'entity_id' => $property->id, 'value' => 'Kept',
        ]);
        $public = $this->fields()->publicValues('property', $property->id);
        $this->assertSame('New Label', $public[0]['fields'][0]['label']);
        $this->assertSame('Kept', $public[0]['fields'][0]['value']);
    }

    // ---- 63 invalid property-type applicability rejected ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_invalid_property_type_applicability_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.custom-fields.store', absolute: false),
            [
                'entity_type' => 'property', 'name' => 'Scoped Field', 'field_type' => 'text',
                'property_type_ids' => [999999],
            ]
        )->assertSessionHasErrors('property_type_ids.0');

        $this->assertDatabaseMissing('hotel_custom_field_definitions', ['name' => 'Scoped Field']);
    }

    // ---- 64 unknown submitted definitions safe ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unknown_submitted_definitions_safe(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => $property->name, 'custom_fields' => [999999 => 'Ghost']])
        )->assertSessionHasErrors('custom_fields');

        $this->assertDatabaseMissing('hotel_custom_field_values', ['value' => 'Ghost']);
    }

    // ---- 65 transactional consistency on validation failure ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_transactional_consistency_on_validation_failure(): void
    {
        $def = $this->makeSelectDef();
        $property = $this->propertyFor();
        $original = $property->name;

        $this->actingAs($this->makeAdmin())->put(
            route('admin.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => 'Changed Name', 'custom_fields' => [$def->id => 'bogus-option']])
        )->assertSessionHasErrors('custom_fields.'.$def->id);

        $this->assertSame($original, $property->fresh()->name);
        $this->assertDatabaseMissing('hotel_custom_field_values', ['definition_id' => $def->id]);
    }
}
