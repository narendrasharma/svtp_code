<?php

namespace Tests\Feature\Hotel;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\HotelAmenity;
use App\Models\HotelBedType;
use App\Models\HotelRoomType;
use App\Models\HotelRoomUnit;
use App\Models\Property;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelRoomService;
use App\Support\AdminNavigation;
use App\Support\ModuleManager;
use Database\Seeders\HotelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HotelRoomTest extends TestCase
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

    protected function roomFor(Property $property, array $overrides = []): HotelRoomType
    {
        return HotelRoomType::factory()->create(array_merge([
            'property_id' => $property->id,
            'status' => RoomTypeStatus::Active->value,
        ], $overrides))->fresh();
    }

    // ---- 1 module disabled blocks admin --------------------------------

    public function test_module_disabled_blocks_admin_room_routes(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);
        $property = Property::factory()->create();

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.room-types.index', $property, absolute: false)
        )->assertNotFound();
        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.room-units.index', $property, absolute: false)
        )->assertNotFound();
    }

    // ---- 2 module disabled blocks vendor ----------------------------------

    public function test_module_disabled_blocks_vendor_room_routes(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);
        $vendor = $this->makeVendor();
        $property = Property::factory()->create(['vendor_profile_id' => $vendor->vendorProfile->id]);

        $this->actingAs($vendor)->get(
            route('vendor.hotel.room-types.index', $property, absolute: false)
        )->assertNotFound();
    }

    // ---- 3 admin can access --------------------------------------------------

    public function test_admin_can_access_room_types(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.room-types.index', $property, absolute: false)
        )->assertOk();
        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.room-units.index', $property, absolute: false)
        )->assertOk();
        $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.bed-types.index', absolute: false)
        )->assertOk();
    }

    // ---- 4 vendor can access own -------------------------------------------------

    public function test_vendor_can_access_own_room_types(): void
    {
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->get(
            route('vendor.hotel.room-types.index', $property, absolute: false)
        )->assertOk();
    }

    // ---- 5 vendor cannot access foreign ------------------------------------------------

    public function test_vendor_cannot_access_foreign_room_type(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $room = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        $this->actingAs($vendorB)->get(
            route('vendor.hotel.room-types.show', $room, absolute: false)
        )->assertNotFound();
        $this->actingAs($vendorB)->get(
            route('vendor.hotel.room-types.edit', $room, absolute: false)
        )->assertNotFound();
    }

    // ---- 6 vendor cannot create for foreign property --------------------------------------------------

    public function test_vendor_cannot_create_room_type_for_foreign_property(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $property = $this->propertyFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload()
        )->assertNotFound();

        $this->assertSame(0, $property->roomTypes()->count());
    }

    // ---- 7 admin can create ----------------------------------------------------------------------------

    public function test_admin_can_create_room_type(): void
    {
        $property = $this->propertyFor();

        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['beds' => [
                ['bed_type_id' => HotelBedType::where('slug', 'king')->firstOrFail()->id, 'quantity' => 1],
            ]])
        );

        $response->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertSame('deluxe-king-room', $room->slug);
        $this->assertSame('1 × King', $room->bed_summary);
    }

    // ---- 8 vendor can create for own -------------------------------------------------------------------------------

    public function test_vendor_can_create_room_type_for_own_property(): void
    {
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload()
        )->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertSame('Deluxe King Room', $room->name);
    }

    // ---- 9 property relation validated ------------------------------------------------------------------------------------------

    public function test_room_type_property_relation_is_validated(): void
    {
        $vendor = $this->makeVendor();
        $own = $this->propertyFor($vendor->vendorProfile);
        $foreign = $this->propertyFor($this->makeVendor()->vendorProfile);
        $foreignRoom = $this->roomFor($foreign);

        // Unit room_type from another property is rejected.
        $response = $this->actingAs($vendor)->post(
            route('vendor.hotel.room-units.store', $own, absolute: false),
            ['room_type_id' => $foreignRoom->id, 'unit_name' => '101', 'status' => 'active']
        );

        $response->assertStatus(422);
        $this->assertSame(0, $own->roomUnits()->count());
    }

    // ---- 10 name required -----------------------------------------------------------------------------------------------------------------

    public function test_room_type_name_is_required(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['name' => ''])
        )->assertSessionHasErrors('name');
    }

    // ---- 11 slug generated ------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_type_slug_is_generated(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['name' => 'Standard Twin Room'])
        )->assertRedirect();

        $this->assertSame('standard-twin-room', $property->roomTypes()->firstOrFail()->slug);
    }

    // ---- 12 duplicate slug across properties ---------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_duplicate_slug_allowed_across_properties(): void
    {
        $admin = $this->makeAdmin();
        $propertyA = $this->propertyFor();
        $propertyB = $this->propertyFor();

        foreach ([$propertyA, $propertyB] as $property) {
            $this->actingAs($admin)->post(
                route('admin.hotel.room-types.store', $property, absolute: false),
                $this->roomPayload()
            )->assertRedirect();
        }

        $this->assertSame('deluxe-king-room', $propertyA->roomTypes()->firstOrFail()->slug);
        $this->assertSame('deluxe-king-room', $propertyB->fresh()->roomTypes()->firstOrFail()->slug);
    }

    // ---- 13 duplicate slug within property ----------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_duplicate_slug_within_property_handled_safely(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();

        foreach ([1, 2] as $i) {
            $this->actingAs($admin)->post(
                route('admin.hotel.room-types.store', $property, absolute: false),
                $this->roomPayload()
            )->assertRedirect();
        }

        $slugs = $property->roomTypes()->orderBy('id')->pluck('slug')->all();
        $this->assertCount(2, array_unique($slugs));
        $this->assertSame('deluxe-king-room', $slugs[0]);
    }

    // ---- 14 max occupancy validated ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_max_occupancy_is_validated(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['max_occupancy' => 0])
        )->assertSessionHasErrors('max_occupancy');
    }

    // ---- 15 max adults validated --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_max_adults_is_validated(): void
    {
        $property = $this->propertyFor();

        // More adults than total occupancy is incoherent.
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['max_adults' => 5, 'max_occupancy' => 3])
        )->assertSessionHasErrors('max_adults');
    }

    // ---- 16 max children validated -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_max_children_is_validated(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['max_children' => 9, 'max_occupancy' => 3])
        )->assertSessionHasErrors('max_children');
    }

    // ---- 17 negative occupancy rejected ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_negative_occupancy_is_rejected(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['max_adults' => -1])
        )->assertSessionHasErrors('max_adults');
    }

    // ---- 18 room size positive -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_size_must_be_positive(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['size_value' => 0, 'size_unit' => 'sqm'])
        )->assertSessionHasErrors('size_value');

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['name' => 'Sized Room', 'size_value' => 32.5, 'size_unit' => 'sqm'])
        )->assertRedirect();
    }

    // ---- 19 valid size unit ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_valid_size_unit_is_accepted(): void
    {
        $property = $this->propertyFor();

        foreach (['sqm', 'sqft'] as $unit) {
            $this->actingAs($this->makeAdmin())->post(
                route('admin.hotel.room-types.store', $property, absolute: false),
                $this->roomPayload(['name' => 'Unit '.$unit, 'size_value' => 20, 'size_unit' => $unit])
            )->assertRedirect();
        }

        $this->assertSame(['sqm', 'sqft'], $property->roomTypes()->orderBy('id')->pluck('size_unit')->all());
    }

    // ---- 20 invalid size unit ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_invalid_size_unit_is_rejected(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['size_value' => 20, 'size_unit' => 'acres'])
        )->assertSessionHasErrors('size_unit');
    }

    // ---- 21 aggregate mode --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_aggregate_inventory_mode_works(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['inventory_mode' => 'aggregate', 'total_units' => 12])
        )->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertSame('aggregate', $room->inventory_mode);
        $this->assertSame(12, (int) $room->total_units);
        $this->assertSame(12, $room->capacityUnits());
    }

    // ---- 22 aggregate total_units required ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_aggregate_mode_requires_total_units(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['inventory_mode' => 'aggregate', 'total_units' => null])
        )->assertSessionHasErrors('total_units');
    }

    // ---- 23 units mode ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_units_inventory_mode_works(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();

        $this->actingAs($admin)->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['name' => 'Unit Suite', 'inventory_mode' => 'units', 'total_units' => null])
        )->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertSame('units', $room->inventory_mode);
        $this->assertNull($room->total_units);
        $this->assertSame(0, $room->capacityUnits());

        $this->actingAs($admin)->post(
            route('admin.hotel.room-units.store', $property, absolute: false),
            ['room_type_id' => $room->id, 'unit_name' => '101', 'status' => 'active']
        )->assertRedirect();

        $this->assertSame(1, $room->fresh()->capacityUnits());
    }

    // ---- 24 active visible publicly -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_active_room_type_is_visible_publicly(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['name' => 'Public Deluxe Room', 'status' => RoomTypeStatus::Active->value]);

        $response = $this->get(route('hotels.show', $property->slug, absolute: false));
        $response->assertOk();
        $response->assertSee('Public Deluxe Room', false);
    }

    // ---- 25 inactive hidden ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inactive_room_type_is_hidden_publicly(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['name' => 'Hidden Quiet Room', 'status' => RoomTypeStatus::Inactive->value]);

        $this->get(route('hotels.show', $property->slug, absolute: false))->assertDontSee('Hidden Quiet Room', false);
    }

    // ---- 26 unpublished property rooms hidden -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unpublished_property_rooms_are_hidden(): void
    {
        $property = $this->propertyFor(null, ['status' => PropertyStatus::Draft->value]);
        $this->roomFor($property, ['name' => 'Draft Property Room', 'status' => RoomTypeStatus::Active->value]);

        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
    }

    // ---- 27 order respected ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_type_order_is_respected_publicly(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['name' => 'Zulu Room Alpha', 'sort_order' => 20, 'status' => RoomTypeStatus::Active->value]);
        $this->roomFor($property, ['name' => 'Alpha Room Beta', 'sort_order' => 5, 'status' => RoomTypeStatus::Active->value]);

        $content = $this->get(route('hotels.show', $property->slug, absolute: false))->getContent();

        $this->assertNotFalse(strpos($content, 'Alpha Room Beta'));
        $this->assertNotFalse(strpos($content, 'Zulu Room Alpha'));
        $this->assertTrue(strpos($content, 'Alpha Room Beta') < strpos($content, 'Zulu Room Alpha'));
    }

    // ---- 28 admin can manage bed types -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_can_manage_bed_types(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.bed-types.store', absolute: false),
            ['name' => 'Murphy Bed']
        )->assertRedirect();

        $this->assertDatabaseHas('hotel_bed_types', ['slug' => 'murphy-bed']);
    }

    // ---- 29 unauthorized staff cannot ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unauthorized_staff_cannot_manage_bed_types(): void
    {
        $this->get(route('admin.hotel.bed-types.index', absolute: false))->assertRedirect();

        $vendor = $this->makeVendor();
        $this->actingAs($vendor)->post(
            route('admin.hotel.bed-types.store', absolute: false),
            ['name' => 'Nope Bed']
        )->assertForbidden();
    }

    // ---- 30 bed config attached ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_bed_configuration_is_attached(): void
    {
        $property = $this->propertyFor();
        $king = HotelBedType::where('slug', 'king')->firstOrFail();
        $single = HotelBedType::where('slug', 'single')->firstOrFail();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['beds' => [
                ['bed_type_id' => $king->id, 'quantity' => 1],
                ['bed_type_id' => $single->id, 'quantity' => 2],
            ]])
        )->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertSame('2 × Single, 1 × King', $room->bed_summary);
        $this->assertSame(2, $room->bedTypes()->count());
    }

    // ---- 31 multiple bed types --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_multiple_bed_types_attach(): void
    {
        $property = $this->propertyFor();
        $beds = HotelBedType::whereIn('slug', ['king', 'twin', 'bunk-bed'])->orderBy('id')->get();

        $payload = $this->roomPayload(['beds' => $beds->map(fn ($bed): array => [
            'bed_type_id' => $bed->id, 'quantity' => 2,
        ])->all()]);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $payload
        )->assertRedirect();

        $this->assertSame(3, $property->roomTypes()->firstOrFail()->bedTypes()->count());
    }

    // ---- 32 invalid/inactive bed rejected ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_invalid_bed_type_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $inactive = HotelBedType::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['beds' => [['bed_type_id' => 999999, 'quantity' => 1]]])
        )->assertSessionHasErrors('beds.0.bed_type_id');

        $this->actingAs($admin)->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['name' => 'Inactive Bed Room', 'beds' => [['bed_type_id' => $inactive->id, 'quantity' => 1]]])
        )->assertSessionHasErrors('beds');
    }

    // ---- 33 quantity positive --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_bed_quantity_must_be_positive(): void
    {
        $property = $this->propertyFor();
        $king = HotelBedType::where('slug', 'king')->firstOrFail();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['beds' => [['bed_type_id' => $king->id, 'quantity' => 0]]])
        )->assertSessionHasErrors('beds.0.quantity');
    }

    // ---- 34 room amenities attach -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_amenities_attach(): void
    {
        $property = $this->propertyFor();
        $ids = HotelAmenity::whereIn('slug', ['television', 'private-bathroom'])->pluck('id')->all();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['amenity_ids' => $ids])
        )->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertEqualsCanonicalizing($ids, $room->amenities()->pluck('hotel_amenities.id')->all());
    }

    // ---- 35 property-only amenity rejected --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_only_amenity_cannot_attach_to_room(): void
    {
        $property = $this->propertyFor();
        $pool = HotelAmenity::where('slug', 'swimming-pool')->firstOrFail();
        $this->assertSame('property', $pool->scope);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['amenity_ids' => [$pool->id]])
        )->assertSessionHasErrors('amenity_ids');
    }

    // ---- 36 inactive room amenity rejected ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inactive_room_amenity_is_rejected(): void
    {
        $property = $this->propertyFor();
        $inactive = HotelAmenity::factory()->create(['is_active' => false, 'scope' => 'room']);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['amenity_ids' => [$inactive->id]])
        )->assertSessionHasErrors('amenity_ids');
    }

    // ---- 37 primary image handled --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_primary_image_is_handled(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        foreach (['ra.jpg', 'rb.jpg'] as $file) {
            $this->actingAs($admin)->post(
                route('admin.hotel.room-types.images.store', $room, absolute: false),
                ['image' => UploadedFile::fake()->image($file)]
            )->assertRedirect();
        }

        $images = $room->fresh()->images()->orderBy('sort_order')->get();
        $this->assertTrue((bool) $images[0]->is_primary);
        $this->assertFalse((bool) $images[1]->is_primary);

        $this->actingAs($admin)->patch(
            route('admin.hotel.room-types.images.primary', [$room, $images[1]], absolute: false)
        )->assertRedirect();

        $this->assertTrue((bool) $images[1]->fresh()->is_primary);
        $this->assertFalse((bool) $images[0]->fresh()->is_primary);
    }

    // ---- 38 gallery relation works ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_gallery_relation_works(): void
    {
        Storage::fake('public');
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.images.store', $room, absolute: false),
            ['image' => UploadedFile::fake()->image('suite.jpg'), 'alt_text' => 'Suite view']
        )->assertRedirect();

        $image = $room->fresh()->images()->firstOrFail();
        $this->assertSame('Suite view', $image->alt_text);
        Storage::disk('public')->assertExists($image->path);
    }

    // ---- 39 unsafe file rejected ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unsafe_room_file_is_rejected(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.images.store', $room, absolute: false),
            ['image' => UploadedFile::fake()->create('evil.txt', 10, 'text/plain')]
        )->assertSessionHasErrors('image');
    }

    // ---- 40 sort order persisted -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_gallery_sort_order_is_persisted(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        foreach (['s1.jpg', 's2.jpg', 's3.jpg'] as $file) {
            $this->actingAs($admin)->post(
                route('admin.hotel.room-types.images.store', $room, absolute: false),
                ['image' => UploadedFile::fake()->image($file)]
            )->assertRedirect();
        }

        $this->assertSame([0, 1, 2], $room->fresh()->images()->orderBy('sort_order')->pluck('sort_order')->all());
    }

    // ---- 41 vendor cannot edit foreign gallery ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_edit_foreign_room_gallery(): void
    {
        Storage::fake('public');
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $room = $this->roomFor($this->propertyFor($vendorA->vendorProfile));

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.room-types.images.store', $room, absolute: false),
            ['image' => UploadedFile::fake()->image('hack.jpg')]
        )->assertNotFound();

        $this->assertSame(0, $room->fresh()->images()->count());
    }

    // ---- 42 admin can create unit -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_can_create_room_unit(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-units.store', $property, absolute: false),
            ['room_type_id' => $room->id, 'unit_name' => '101', 'floor' => '1', 'status' => 'active']
        )->assertRedirect();

        $unit = $property->roomUnits()->firstOrFail();
        $this->assertSame('101', $unit->unit_name);
        $this->assertSame($room->id, (int) $unit->room_type_id);
    }

    // ---- 43 vendor can create own unit ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_can_create_own_room_unit(): void
    {
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);
        $room = $this->roomFor($property);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.room-units.store', $property, absolute: false),
            ['room_type_id' => $room->id, 'unit_name' => 'Villa A', 'status' => 'active']
        )->assertRedirect();

        $this->assertSame('Villa A', $property->roomUnits()->firstOrFail()->unit_name);
    }

    // ---- 44 vendor cannot create for foreign -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_create_unit_for_foreign_property(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $property = $this->propertyFor($vendorA->vendorProfile);
        $room = $this->roomFor($property);

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.room-units.store', $property, absolute: false),
            ['room_type_id' => $room->id, 'unit_name' => '999', 'status' => 'active']
        )->assertNotFound();

        $this->assertSame(0, $property->roomUnits()->count());
    }

    // ---- 45 room type must belong to same property ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unit_room_type_must_belong_to_same_property(): void
    {
        $admin = $this->makeAdmin();
        $propertyA = $this->propertyFor();
        $propertyB = $this->propertyFor();
        $foreignRoom = $this->roomFor($propertyB);

        $this->actingAs($admin)->post(
            route('admin.hotel.room-units.store', $propertyA, absolute: false),
            ['room_type_id' => $foreignRoom->id, 'unit_name' => 'X1', 'status' => 'active']
        )->assertStatus(422);

        $this->assertSame(0, $propertyA->roomUnits()->count());
    }

    // ---- 46 unit name uniqueness within property ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unit_name_unique_within_property(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($admin)->post(
            route('admin.hotel.room-units.store', $property, absolute: false),
            ['room_type_id' => $room->id, 'unit_name' => '101', 'status' => 'active']
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('admin.hotel.room-units.store', $property, absolute: false),
            ['room_type_id' => $room->id, 'unit_name' => '101', 'status' => 'active']
        )->assertSessionHasErrors('unit_name');
    }

    // ---- 47 same unit name at another property --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_same_unit_name_allowed_at_another_property(): void
    {
        $admin = $this->makeAdmin();
        $propertyA = $this->propertyFor();
        $propertyB = $this->propertyFor();

        foreach ([$propertyA, $propertyB] as $property) {
            $room = $this->roomFor($property);
            $this->actingAs($admin)->post(
                route('admin.hotel.room-units.store', $property, absolute: false),
                ['room_type_id' => $room->id, 'unit_name' => '101', 'status' => 'active']
            )->assertRedirect();
        }

        $this->assertSame(1, $propertyA->roomUnits()->count());
        $this->assertSame(1, $propertyB->fresh()->roomUnits()->count());
    }

    // ---- 48 maintenance persists -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_maintenance_status_persists(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($admin)->post(
            route('admin.hotel.room-units.store', $property, absolute: false),
            ['room_type_id' => $room->id, 'unit_name' => '102', 'status' => 'maintenance', 'notes' => 'AC repair']
        )->assertRedirect();

        $unit = $property->roomUnits()->firstOrFail();
        $this->assertSame('maintenance', $unit->status);
        $this->assertSame('AC repair', $unit->notes);
    }

    // ---- 49 out-of-service persists ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_out_of_service_status_persists(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $unit = app(HotelRoomService::class)->createUnit($property, [
            'room_type_id' => $room->id, 'unit_name' => '103', 'status' => 'out_of_service',
        ]);

        $this->assertSame('out_of_service', $unit->status);

        app(HotelRoomService::class)->updateUnit($property, $unit, [
            'room_type_id' => $room->id, 'unit_name' => '103', 'status' => 'active',
        ]);

        $this->assertSame('active', $unit->fresh()->status);
    }

    // ---- 50 units optional ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_physical_units_are_optional(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, ['inventory_mode' => HotelRoomType::INVENTORY_AGGREGATE, 'total_units' => 7]);

        $this->assertSame(0, $room->units()->count());
        $this->assertSame(7, $room->capacityUnits());
        $this->assertSame(PropertyStatus::Published->value, $property->fresh()->status);
    }

    // ---- 51 public excludes unit notes --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_payload_excludes_unit_notes(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, [
            'name' => 'Secret Notes Room',
            'status' => RoomTypeStatus::Active->value,
            'inventory_mode' => HotelRoomType::INVENTORY_UNITS,
        ]);
        app(HotelRoomService::class)->createUnit($property, [
            'room_type_id' => $room->id, 'unit_name' => 'SecretUnit99', 'status' => 'active',
            'notes' => 'SecretInternalNoteMarker',
        ]);

        $content = $this->get(route('hotels.show', $property->slug, absolute: false))->getContent();

        $this->assertStringContainsString('Secret Notes Room', $content);
        $this->assertStringNotContainsString('SecretUnit99', $content);
        $this->assertStringNotContainsString('SecretInternalNoteMarker', $content);
    }

    // ---- 52 public displays active rooms ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_detail_displays_active_rooms(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['name' => 'Showcase Suite', 'status' => RoomTypeStatus::Active->value]);

        $response = $this->get(route('hotels.show', $property->slug, absolute: false));
        $response->assertOk();
        $response->assertSee('Showcase Suite', false);
        // Room-card payload keys prove the rooms section rendered.
        $response->assertSee('max_occupancy', false);
    }

    // ---- 53 public excludes inactive internals --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_payload_excludes_inactive_internals(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['name' => 'Invisible Draft Room', 'status' => RoomTypeStatus::Draft->value]);

        $content = $this->get(route('hotels.show', $property->slug, absolute: false))->getContent();

        $this->assertStringNotContainsString('Invisible Draft Room', $content);
        $this->assertStringNotContainsString('total_units', $content);
        $this->assertStringNotContainsString('inventory_mode', $content);
    }

    // ---- 54 creation audited -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_type_creation_is_audited(): void
    {
        $property = $this->propertyFor();

        $before = DB::table('activity_logs')->where('event', 'hotel_room_type.created')->count();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload()
        )->assertRedirect();

        $this->assertSame($before + 1, DB::table('activity_logs')->where('event', 'hotel_room_type.created')->count());
    }

    // ---- 55 reads do not audit-spam --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_room_reads_write_no_audit_events(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property, ['status' => RoomTypeStatus::Active->value]);

        $before = DB::table('activity_logs')->count();
        $this->get(route('hotels.show', $property->slug, absolute: false))->assertOk();

        $this->assertSame($before, DB::table('activity_logs')->count());
    }

    // ---- 56 nav hides when disabled ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_module_disabled_navigation_is_hidden(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $groups = AdminNavigation::filteredFor($this->makeAdmin());

        $this->assertNull(collect($groups)->firstWhere('key', 'hotels'));
    }

    // ---- 57 admin nav displays ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_nav_displays_when_enabled(): void
    {
        $groups = AdminNavigation::filteredFor($this->makeAdmin());
        $hotels = collect($groups)->firstWhere('key', 'hotels');

        $this->assertNotNull($hotels);
        $this->assertNotEmpty($hotels['items']);
    }

    // ---- 58 vendor nav displays ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_nav_displays_when_enabled(): void
    {
        $response = $this->actingAs($this->makeVendor())->get(
            route('vendor.hotel.properties.index', absolute: false)
        );

        // Vendor portal resolves (nav entry points at this real route).
        $response->assertOk();
        $response->assertSee('properties', false);
    }

    // ---- 59 seeder idempotent --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_demo_seeder_creates_room_data_idempotently(): void
    {
        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(5, HotelRoomType::where('slug', 'like', 'demo-%')->count());

        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(5, HotelRoomType::where('slug', 'like', 'demo-%')->count());
        $this->assertSame(2, HotelRoomUnit::whereHas('property', fn ($q) => $q->where('slug', 'demo-harbour-hotel'))->count());
    }

    // ---- 60 factories many rooms ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_factories_generate_many_rooms_without_exhaustion(): void
    {
        $property = $this->propertyFor();

        for ($i = 0; $i < 15; $i++) {
            HotelRoomType::factory()->create(['property_id' => $property->id]);
            HotelRoomUnit::factory()->create([
                'property_id' => $property->id,
                'room_type_id' => $property->roomTypes()->inRandomOrder()->first()->id,
            ]);
        }

        $this->assertSame(15, $property->roomTypes()->count());
        $this->assertSame(15, $property->roomUnits()->count());
    }

    // ---- 61 cross-vendor binding ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_cross_vendor_room_binding_is_protected(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $room = $this->roomFor($this->propertyFor($vendorA->vendorProfile));
        $original = $room->name;

        $this->actingAs($vendorB)->put(
            route('vendor.hotel.room-types.update', $room, absolute: false),
            $this->roomPayload(['name' => 'Hijacked'])
        )->assertNotFound();

        $this->actingAs($vendorB)->delete(
            route('vendor.hotel.room-types.destroy', $room, absolute: false)
        )->assertNotFound();

        $this->assertSame($original, $room->fresh()->name);
    }

    // (placeholder retained for numbering parity with the phase brief)

    // ---- 62 forged property_id --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_forged_property_id_is_rejected(): void
    {
        $vendor = $this->makeVendor();
        $own = $this->propertyFor($vendor->vendorProfile);
        $foreign = $this->propertyFor($this->makeVendor()->vendorProfile);

        // Units endpoint takes property from the URL; a forged room_type_id
        // from another property must fail rather than attach.
        $foreignRoom = $this->roomFor($foreign);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.room-units.store', $own, absolute: false),
            ['room_type_id' => $foreignRoom->id, 'unit_name' => 'Z9', 'status' => 'active']
        )->assertStatus(422);

        $this->assertSame(0, $own->roomUnits()->count());
        $this->assertSame(0, $foreign->roomUnits()->count());
    }

    // ---- 63 forged vendor ownership -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_forged_vendor_ownership_is_impossible(): void
    {
        $vendor = $this->makeVendor();
        $other = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['vendor_profile_id' => $other->vendorProfile->id])
        )->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertSame($property->id, (int) $room->property_id);
        $this->assertSame($vendor->vendorProfile->id, (int) $room->property->vendor_profile_id);
    }

    // ---- 64 descriptions sanitize script --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_descriptions_strip_executable_script(): void
    {
        $property = $this->propertyFor();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.room-types.store', $property, absolute: false),
            $this->roomPayload(['description' => '<p>Cozy.</p><script>alert("x")</script>'])
        )->assertRedirect();

        $room = $property->roomTypes()->firstOrFail();
        $this->assertStringNotContainsString('<script>', (string) $room->description);
        $this->assertStringContainsString('Cozy.', (string) $room->description);
    }

    // ---- 65 archiving room type ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_archiving_room_type_follows_safe_convention(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        $this->actingAs($admin)->delete(
            route('admin.hotel.room-types.destroy', $room, absolute: false)
        )->assertRedirect();

        $this->assertSoftDeleted('hotel_room_types', ['id' => $room->id]);
        $this->assertSame(0, $property->roomTypes()->count());
        $this->get(route('hotels.show', $property->slug, absolute: false))->assertDontSee($room->name, false);
    }

    // ---- 66 unit archive ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_room_unit_archive_follows_safe_convention(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $room = $this->roomFor($property);
        $unit = app(HotelRoomService::class)->createUnit($property, [
            'room_type_id' => $room->id,
            'unit_name' => 'Archive Me',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->delete(
            route('admin.hotel.room-units.destroy', $unit, absolute: false)
        )->assertRedirect();

        $this->assertSoftDeleted('hotel_room_units', ['id' => $unit->id]);
    }

    // ---- 67 no pricing fields ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_no_pricing_fields_exist_on_rooms(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property);

        foreach (['price', 'rate', 'amount', 'cost', 'currency'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('hotel_room_types', $column),
                "Premature pricing column {$column}."
            );
        }

        $this->assertFalse(array_key_exists('price', $room->toArray()));
    }

    // ---- 68 no availability rows ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_no_availability_rows_are_created(): void
    {
        $property = $this->propertyFor();
        $this->roomFor($property);

        // The hotel inventory and pricing schemas are now installed; room
        // creation must still leave their operational rows empty.
        $this->assertDatabaseCount('hotel_room_inventories', 0);
        $this->assertDatabaseCount('hotel_rate_plans', 0);
    }

    // ---- 69 property amenities still work ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_existing_property_amenities_still_work(): void
    {
        $property = $this->propertyFor();
        $pool = HotelAmenity::where('slug', 'swimming-pool')->firstOrFail();

        $property->amenities()->sync([$pool->id]);
        $this->assertSame(1, $property->amenities()->count());

        // Room sync must not disturb property amenities.
        $room = $this->roomFor($property);
        app(HotelRoomService::class)->syncRoomAmenities($room, []);
        $this->assertSame(1, $property->fresh()->amenities()->count());

        $response = $this->get(route('hotels.show', $property->slug, absolute: false));
        $response->assertSee('Swimming Pool', false);
    }

    // ---- 70 property page still renders ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_page_still_renders_with_rooms(): void
    {
        $property = $this->propertyFor();
        $room = $this->roomFor($property, ['name' => 'Render Check Suite', 'status' => RoomTypeStatus::Active->value]);
        app(HotelRoomService::class)->syncBeds($room, [
            ['bed_type_id' => HotelBedType::where('slug', 'queen')->firstOrFail()->id, 'quantity' => 1],
        ]);
        app(HotelRoomService::class)->refreshBedSummary($room->fresh());

        $response = $this->get(route('hotels.show', $property->slug, absolute: false));
        $response->assertOk();
        $response->assertSee('Render Check Suite', false);
        $response->assertSee('Queen', false);
        $response->assertSee($property->name, false);
        $this->assertSame('1 × Queen', $room->fresh()->bed_summary);
    }
}
