<?php

namespace Tests\Feature\Hotel;

use App\Enums\PropertyStatus;
use App\Models\HotelAmenity;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use App\Services\HotelPropertyService;
use App\Support\AdminNavigation;
use App\Support\HotelSettings;
use App\Support\ModuleManager;
use Database\Seeders\HotelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HotelCoreTest extends TestCase
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
            'property_type_id' => PropertyType::where('slug', 'hotel')->firstOrFail()->id,
            'name' => 'Grand Test Hotel',
            'address_line_1' => '12 Test Street',
            'country_code' => 'US',
        ], $overrides);
    }

    protected function propertyFor(?VendorProfile $vendor = null, array $overrides = []): Property
    {
        $property = Property::factory()->create(array_merge([
            'vendor_profile_id' => $vendor?->id,
            'status' => PropertyStatus::Draft->value,
        ], $overrides));

        return $property->fresh();
    }

    // ---- 1 module disabled blocks admin -------------------------------

    public function test_module_disabled_blocks_admin_hotel_routes(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeAdmin())->get(route('admin.hotel.properties.index', absolute: false))->assertNotFound();
        $this->actingAs($this->makeAdmin())->get(route('admin.hotel.settings.index', absolute: false))->assertNotFound();
    }

    // ---- 2 module disabled blocks vendor ---------------------------------

    public function test_module_disabled_blocks_vendor_hotel_routes(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($this->makeVendor())->get(route('vendor.hotel.properties.index', absolute: false))->assertNotFound();
    }

    // ---- 3 module disabled blocks public ----------------------------------

    public function test_module_disabled_blocks_public_hotel_routes(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->get('/hotels')->assertNotFound();
        $this->get('/hotels/anything-here')->assertNotFound();
    }

    // ---- 4 admin can access -------------------------------------------------

    public function test_admin_can_access_hotel_properties(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('admin.hotel.properties.index', absolute: false))->assertOk();
        $this->actingAs($this->makeAdmin())->get(route('admin.hotel.property-types.index', absolute: false))->assertOk();
        $this->actingAs($this->makeAdmin())->get(route('admin.hotel.amenities.index', absolute: false))->assertOk();
    }

    // ---- 5 vendor can access own -----------------------------------------------

    public function test_vendor_can_access_own_properties(): void
    {
        $this->actingAs($this->makeVendor())->get(route('vendor.hotel.properties.index', absolute: false))->assertOk();
    }

    // ---- 6 vendor cannot access foreign ---------------------------------------------

    public function test_vendor_cannot_access_foreign_property(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $property = $this->propertyFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)->get(route('vendor.hotel.properties.show', $property, absolute: false))->assertNotFound();
        $this->actingAs($vendorB)->get(route('vendor.hotel.properties.edit', $property, absolute: false))->assertNotFound();
    }

    // ---- 7 vendor cannot update foreign ---------------------------------------------------

    public function test_vendor_cannot_update_foreign_property(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $property = $this->propertyFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)->put(
            route('vendor.hotel.properties.update', $property, absolute: false),
            $this->payload(['name' => 'Hijacked Name'])
        )->assertNotFound();

        $this->assertSame($property->name, $property->fresh()->name);
    }

    // ---- 8 vendor cannot assign vendor_profile_id ----------------------------------------------------------------

    public function test_vendor_cannot_assign_vendor_profile_id(): void
    {
        $vendor = $this->makeVendor();
        $other = $this->makeVendor();

        $response = $this->actingAs($vendor)->post(
            route('vendor.hotel.properties.store', absolute: false),
            $this->payload(['vendor_profile_id' => $other->vendorProfile->id])
        );

        $response->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertSame($vendor->vendorProfile->id, (int) $property->vendor_profile_id);
    }

    // ---- 9 admin can create platform property ---------------------------------------------------------------------------------

    public function test_admin_can_create_platform_property(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload()
        );

        $response->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertNull($property->vendor_profile_id);
        $this->assertSame(PropertyStatus::Draft->value, $property->status);
    }

    // ---- 10 admin can create vendor property ------------------------------------------------------------------------------------------------

    public function test_admin_can_create_vendor_property(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();

        $response = $this->actingAs($admin)->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['vendor_profile_id' => $vendor->vendorProfile->id, 'publish_directly' => true])
        );

        $response->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertSame($vendor->vendorProfile->id, (int) $property->vendor_profile_id);
        $this->assertSame(PropertyStatus::Published->value, $property->status);
        $this->assertNotNull($property->published_at);
    }

    // ---- 11 vendor-created gets ownership ------------------------------------------------------------------------------------------------------------------

    public function test_vendor_created_property_gets_correct_ownership(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($vendor)->post(
            route('vendor.hotel.properties.store', absolute: false),
            $this->payload()
        )->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertSame($vendor->vendorProfile->id, (int) $property->vendor_profile_id);
        $this->assertSame(PropertyStatus::Draft->value, $property->status);
    }

    // ---- 12 name required ------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_name_is_required(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['name' => ''])
        );

        $response->assertSessionHasErrors('name');
    }

    // ---- 13 type validation ------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_type_is_validated(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['property_type_id' => 999999])
        );

        $response->assertSessionHasErrors('property_type_id');
    }

    // ---- 14 star accepts 1-5 -------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_star_rating_accepts_1_to_5(): void
    {
        $admin = $this->makeAdmin();

        foreach ([1, 2, 3, 4, 5] as $stars) {
            $response = $this->actingAs($admin)->post(
                route('admin.hotel.properties.store', absolute: false),
                $this->payload(['name' => 'Star Hotel '.$stars, 'star_rating' => $stars])
            );

            $response->assertRedirect();
            $this->assertSame($stars, (int) Property::latest('id')->firstOrFail()->star_rating);
        }
    }

    // ---- 15 invalid star rejected -------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_invalid_star_rating_is_rejected(): void
    {
        foreach ([0, 6] as $stars) {
            $response = $this->actingAs($this->makeAdmin())->post(
                route('admin.hotel.properties.store', absolute: false),
                $this->payload(['star_rating' => $stars])
            );

            $response->assertSessionHasErrors('star_rating');
        }
    }

    // ---- 16 valid ISO currency -----------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_valid_iso_currency_is_accepted(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['currency' => 'eur'])
        )->assertRedirect();

        $this->assertSame('EUR', Property::latest('id')->firstOrFail()->currency);
    }

    // ---- 17 invalid currency ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_invalid_currency_is_rejected(): void
    {
        foreach (['EURO', '12', 'E'] as $currency) {
            $response = $this->actingAs($this->makeAdmin())->post(
                route('admin.hotel.properties.store', absolute: false),
                $this->payload(['currency' => $currency])
            );

            $response->assertSessionHasErrors('currency');
        }
    }

    // ---- 18 valid timezone -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_valid_timezone_is_accepted(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['timezone' => 'America/New_York'])
        )->assertRedirect();

        $this->assertSame('America/New_York', Property::latest('id')->firstOrFail()->timezone);
    }

    // ---- 19 invalid timezone -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_invalid_timezone_is_rejected(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['timezone' => 'Mars/Olympus'])
        );

        $response->assertSessionHasErrors('timezone');
    }

    // ---- 20 latitude validation ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_latitude_is_validated(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['latitude' => 91])
        );

        $response->assertSessionHasErrors('latitude');

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['name' => 'Lat Hotel', 'latitude' => 40.7128, 'longitude' => -74.006])
        )->assertRedirect();
    }

    // ---- 21 longitude validation -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_longitude_is_validated(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['longitude' => 181])
        );

        $response->assertSessionHasErrors('longitude');
    }

    // ---- 22 slug generated -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_slug_is_generated_from_name(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['name' => 'Seaside Grand Resort'])
        )->assertRedirect();

        $this->assertSame('seaside-grand-resort', Property::latest('id')->firstOrFail()->slug);
    }

    // ---- 23 duplicate slug handled ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_duplicate_slug_is_handled_safely(): void
    {
        $admin = $this->makeAdmin();

        foreach (['Twin Peaks Lodge', 'Twin Peaks Lodge'] as $name) {
            $this->actingAs($admin)->post(
                route('admin.hotel.properties.store', absolute: false),
                $this->payload(['name' => $name])
            )->assertRedirect();
        }

        $slugs = Property::orderBy('id')->pluck('slug')->take(-2)->all();
        $this->assertCount(2, array_unique($slugs));
    }

    // ---- 24 draft not public -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_draft_property_is_not_public(): void
    {
        $property = $this->propertyFor(null, ['status' => PropertyStatus::Draft->value]);

        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
        $this->get('/hotels')->assertDontSee($property->name);
    }

    // ---- 25 pending not public ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_pending_property_is_not_public(): void
    {
        $property = $this->propertyFor(null, ['status' => PropertyStatus::PendingReview->value]);

        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
    }

    // ---- 26 published visible -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_published_property_is_publicly_visible(): void
    {
        $property = $this->propertyFor(null, [
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
            'short_description' => 'Lovely public stay.',
        ]);

        $this->get(route('hotels.show', $property->slug, absolute: false))->assertOk();
        $this->get('/hotels')->assertSee($property->name);
    }

    // ---- 27 inactive not public -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inactive_property_is_not_public(): void
    {
        $property = $this->propertyFor(null, ['status' => PropertyStatus::Inactive->value]);

        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
    }

    // ---- 28 admin publish works -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_publish_action_works(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor(null, ['status' => PropertyStatus::PendingReview->value]);

        $this->actingAs($admin)->post(
            route('admin.hotel.properties.publish', $property, absolute: false)
        )->assertRedirect();

        $fresh = $property->fresh();
        $this->assertSame(PropertyStatus::Published->value, $fresh->status);
        $this->assertNotNull($fresh->published_at);
        $this->get(route('hotels.show', $fresh->slug, absolute: false))->assertOk();
    }

    // ---- 29 vendor cannot self-publish ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_self_publish_when_approval_required(): void
    {
        Setting::setValue('hotel.require_property_approval', '1');
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.properties.submit', $property, absolute: false)
        )->assertRedirect();

        $this->assertSame(PropertyStatus::PendingReview->value, $property->fresh()->status);
        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
    }

    // ---- 30 vendor submission pending + notified -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_submission_becomes_pending_and_notifies(): void
    {
        Notification::fake();
        Setting::setValue('hotel.require_property_approval', '1');
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);

        $this->actingAs($vendor)->post(
            route('vendor.hotel.properties.submit', $property, absolute: false)
        )->assertRedirect();

        $this->assertSame(PropertyStatus::PendingReview->value, $property->fresh()->status);
        Notification::assertSentTo($admin, CrmNotification::class);
    }

    // ---- 31 admin rejection works -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_rejection_works(): void
    {
        $admin = $this->makeAdmin();
        $property = $this->propertyFor(null, ['status' => PropertyStatus::PendingReview->value]);

        $this->actingAs($admin)->post(
            route('admin.hotel.properties.reject', $property, absolute: false),
            ['note' => 'Needs better photos.']
        )->assertRedirect();

        $this->assertSame(PropertyStatus::Rejected->value, $property->fresh()->status);
        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
    }

    // ---- 32 type CRUD protected ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_type_crud_is_permission_protected(): void
    {
        // Guest first: actingAs() persists for later requests in a test.
        $this->get(route('admin.hotel.property-types.index', absolute: false))->assertRedirect();

        $vendor = $this->makeVendor();

        $this->actingAs($vendor)->post(route('admin.hotel.property-types.store', absolute: false), ['name' => 'Nope'])->assertForbidden();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.property-types.store', absolute: false),
            ['name' => 'Ryokan']
        )->assertRedirect();
        $this->assertDatabaseHas('property_types', ['slug' => 'ryokan']);
    }

    // ---- 33 amenity CRUD protected -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_amenity_crud_is_permission_protected(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($vendor)->post(route('admin.hotel.amenities.store', absolute: false), ['name' => 'Nope'])->assertForbidden();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.amenities.store', absolute: false),
            ['name' => 'Rooftop Bar', 'category' => 'Food & Drink']
        )->assertRedirect();
        $this->assertDatabaseHas('hotel_amenities', ['slug' => 'rooftop-bar']);
    }

    // ---- 34 amenities attach -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_amenities_attach_correctly(): void
    {
        $ids = HotelAmenity::whereIn('slug', ['free-wi-fi', 'parking'])->pluck('id')->all();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['amenity_ids' => $ids])
        )->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing($ids, $property->amenities()->pluck('hotel_amenities.id')->all());
    }

    // ---- 35 foreign/inactive amenity safe -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_inactive_and_unknown_amenities_are_safe(): void
    {
        $inactive = HotelAmenity::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['amenity_ids' => [$inactive->id, 999999]])
        );

        $response->assertSessionHasErrors('amenity_ids.1');

        $ok = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['name' => 'Inactive Amenity Hotel', 'amenity_ids' => [$inactive->id]])
        );
        $ok->assertRedirect();
        $this->assertSame(0, Property::latest('id')->firstOrFail()->amenities()->count());
    }

    // ---- 36 gallery upload works ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_gallery_upload_works(): void
    {
        Storage::fake('public');
        $property = $this->propertyFor();

        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.images.store', $property, absolute: false),
            ['image' => UploadedFile::fake()->image('pool.jpg'), 'alt_text' => 'Pool side']
        );

        $response->assertRedirect();
        $image = $property->fresh()->images()->firstOrFail();
        $this->assertSame('Pool side', $image->alt_text);
        Storage::disk('public')->assertExists($image->path);
    }

    // ---- 37 primary image handled ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_primary_image_is_managed(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();

        foreach (['a.jpg', 'b.jpg'] as $file) {
            $this->actingAs($admin)->post(
                route('admin.hotel.properties.images.store', $property, absolute: false),
                ['image' => UploadedFile::fake()->image($file)]
            )->assertRedirect();
        }

        $images = $property->fresh()->images()->orderBy('sort_order')->get();
        $this->assertTrue((bool) $images[0]->is_primary);
        $this->assertFalse((bool) $images[1]->is_primary);

        $this->actingAs($admin)->patch(
            route('admin.hotel.properties.images.primary', [$property, $images[1]], absolute: false)
        )->assertRedirect();

        $this->assertTrue((bool) $images[1]->fresh()->is_primary);
        $this->assertFalse((bool) $images[0]->fresh()->is_primary);
    }

    // ---- 38 unsafe image rejected -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_unsafe_image_type_is_rejected(): void
    {
        $property = $this->propertyFor();

        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.images.store', $property, absolute: false),
            ['image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')]
        );

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, $property->fresh()->images()->count());
    }

    // ---- 39 sort order persisted -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_gallery_sort_order_is_persisted(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();

        foreach (['one.jpg', 'two.jpg', 'three.jpg'] as $file) {
            $this->actingAs($admin)->post(
                route('admin.hotel.properties.images.store', $property, absolute: false),
                ['image' => UploadedFile::fake()->image($file)]
            )->assertRedirect();
        }

        $this->assertSame([0, 1, 2], $property->fresh()->images()->orderBy('sort_order')->pluck('sort_order')->all());
    }

    // ---- 40 vendor cannot edit foreign gallery ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_edit_foreign_gallery(): void
    {
        Storage::fake('public');
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $property = $this->propertyFor($vendorA->vendorProfile);

        $this->actingAs($vendorB)->post(
            route('vendor.hotel.properties.images.store', $property, absolute: false),
            ['image' => UploadedFile::fake()->image('hack.jpg')]
        )->assertNotFound();

        $this->assertSame(0, $property->fresh()->images()->count());
    }

    // ---- 41 contact email validation -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_contact_email_is_validated(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['email' => 'not-an-email'])
        );

        $response->assertSessionHasErrors('email');
    }

    // ---- 42 website validation ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_website_is_validated(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['website' => 'not-a-url'])
        );

        $response->assertSessionHasErrors('website');

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['name' => 'Web Hotel', 'website' => 'https://example.com'])
        )->assertRedirect();
    }

    // ---- 43 check-in persisted ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_check_in_time_is_persisted(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['check_in_time' => '15:30'])
        )->assertRedirect();

        $this->assertSame('15:30', Property::latest('id')->firstOrFail()->check_in_time->format('H:i'));
    }

    // ---- 44 check-out persisted ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_check_out_time_is_persisted(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['check_out_time' => '10:00'])
        )->assertRedirect();

        $this->assertSame('10:00', Property::latest('id')->firstOrFail()->check_out_time->format('H:i'));

        $bad = $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload(['check_in_time' => '3pm'])
        );
        $bad->assertSessionHasErrors('check_in_time');
    }

    // ---- 45 public index only published ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_index_only_shows_published(): void
    {
        $this->propertyFor(null, ['name' => 'Visible Palace Hotel', 'status' => PropertyStatus::Published->value, 'published_at' => now()]);
        $this->propertyFor(null, ['name' => 'Hidden Draft Hotel', 'status' => PropertyStatus::Draft->value]);

        $response = $this->get('/hotels');
        $response->assertOk();
        $response->assertSee('Visible Palace Hotel');
        $response->assertDontSee('Hidden Draft Hotel');
    }

    // ---- 46 public detail excludes internal data ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_detail_excludes_internal_vendor_data(): void
    {
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile, [
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
        ]);

        $response = $this->get(route('hotels.show', $property->slug, absolute: false));
        $response->assertOk();
        $response->assertDontSee('vendor_profile_id');
        $response->assertDontSee('created_by');
        $response->assertDontSee($vendor->email);
    }

    // ---- 47 admin filter status -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_list_can_filter_status(): void
    {
        $this->propertyFor(null, ['name' => 'Filter Draft Stay', 'status' => PropertyStatus::Draft->value]);
        $this->propertyFor(null, ['name' => 'Filter Live Stay', 'status' => PropertyStatus::Published->value, 'published_at' => now()]);

        $response = $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.properties.index', absolute: false).'?status=published'
        );

        $response->assertOk();
        $response->assertSee('Filter Live Stay');
        $response->assertDontSee('Filter Draft Stay');
    }

    // ---- 48 admin filter vendor -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_list_can_filter_vendor(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $this->propertyFor($vendorA->vendorProfile, ['name' => 'Vendor A Stay']);
        $this->propertyFor($vendorB->vendorProfile, ['name' => 'Vendor B Stay']);

        $response = $this->actingAs($this->makeAdmin())->get(
            route('admin.hotel.properties.index', absolute: false).'?vendor_id='.$vendorA->vendorProfile->id
        );

        $response->assertOk();
        $response->assertSee('Vendor A Stay');
        $response->assertDontSee('Vendor B Stay');
    }

    // ---- 49 vendor list never foreign -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_list_never_contains_foreign_property(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $this->propertyFor($vendorA->vendorProfile, ['name' => 'Own Stay Alpha']);
        $this->propertyFor($vendorB->vendorProfile, ['name' => 'Foreign Stay Beta']);

        $response = $this->actingAs($vendorA)->get(route('vendor.hotel.properties.index', absolute: false));

        $response->assertOk();
        $response->assertSee('Own Stay Alpha');
        $response->assertDontSee('Foreign Stay Beta');
    }

    // ---- 50 creation audited ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_creation_is_audited(): void
    {
        $before = DB::table('activity_logs')->where('event', 'property.created')->count();

        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload()
        )->assertRedirect();

        $this->assertSame($before + 1, DB::table('activity_logs')->where('event', 'property.created')->count());
    }

    // ---- 51 public view no audit spam ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_view_writes_no_audit_events(): void
    {
        $property = $this->propertyFor(null, [
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
        ]);

        $before = DB::table('activity_logs')->count();
        $this->get('/hotels')->assertOk();
        $this->get(route('hotels.show', $property->slug, absolute: false))->assertOk();

        $this->assertSame($before, DB::table('activity_logs')->count());
    }

    // ---- 52 submission notification ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_submission_notification_is_emitted(): void
    {
        Notification::fake();
        Setting::setValue('hotel.require_property_approval', '1');
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile);

        app(HotelPropertyService::class)->submit($property, $vendor);

        Notification::assertSentTo($admin, CrmNotification::class);
    }

    // ---- 53 approval notification ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_approval_notification_is_emitted(): void
    {
        Notification::fake();
        $vendor = $this->makeVendor();
        $property = $this->propertyFor($vendor->vendorProfile, ['status' => PropertyStatus::PendingReview->value]);

        app(HotelPropertyService::class)->publish($property, $this->makeAdmin());

        Notification::assertSentTo($vendor->fresh(), CrmNotification::class);
    }

    // ---- 54 SEO props generated -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_seo_props_are_generated_safely(): void
    {
        $property = $this->propertyFor(null, [
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
            'meta_title' => 'Custom SEO Title',
            'short_description' => 'Fallback description.',
        ]);

        $response = $this->get(route('hotels.show', $property->slug, absolute: false));
        $response->assertOk();
        $response->assertSee('Custom SEO Title', false);
        $response->assertSee('hotels', false);
        $response->assertSee($property->slug, false);
    }

    // ---- 55 settings defaults -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_hotel_settings_have_safe_defaults(): void
    {
        foreach (array_keys(HotelSettings::DEFAULTS) as $key) {
            $this->assertNotNull(HotelSettings::get($key), "Missing default for {$key}.");
        }

        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', HotelSettings::defaultCurrency());
        $this->assertTrue(in_array(HotelSettings::defaultTimezone(), timezone_identifiers_list(), true));
        $this->assertGreaterThanOrEqual(6, HotelSettings::perPage());

        $this->actingAs($this->makeAdmin())->get(route('admin.hotel.settings.index', absolute: false))->assertOk();
    }

    // ---- 56 demo seeder idempotent -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(3, Property::where('slug', 'like', 'demo-%')->count());

        $this->seed(HotelDemoSeeder::class);
        $this->assertSame(3, Property::where('slug', 'like', 'demo-%')->count());
        $this->assertSame(1, VendorProfile::where('slug', 'demo-stays')->count());
    }

    // ---- 57 navigation available -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_module_enabled_navigation_is_available(): void
    {
        $groups = AdminNavigation::filteredFor($this->makeAdmin());
        $hotel = collect($groups)->firstWhere('key', 'hotels');

        $this->assertNotNull($hotel);
        $this->assertNotEmpty($hotel['items']);
    }

    // ---- 58 navigation hidden -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_module_disabled_navigation_is_hidden(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);

        $groups = AdminNavigation::filteredFor($this->makeAdmin());

        $this->assertNull(collect($groups)->firstWhere('key', 'hotels'));
    }

    // ---- 59 descriptions strip script -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_property_descriptions_strip_executable_script(): void
    {
        $this->actingAs($this->makeAdmin())->post(
            route('admin.hotel.properties.store', absolute: false),
            $this->payload([
                'description' => '<p>Nice stay.</p><script>alert("x")</script><a href="javascript:evil()">click</a>',
                'house_rules' => '<b>No parties</b><script>evil()</script>',
            ])
        )->assertRedirect();

        $property = Property::latest('id')->firstOrFail();
        $this->assertStringNotContainsString('<script>', (string) $property->description);
        $this->assertStringNotContainsString('javascript:', (string) $property->description);
        $this->assertStringNotContainsString('<script>', (string) $property->house_rules);
        $this->assertStringContainsString('Nice stay.', (string) $property->description);
    }

    // ---- 60 safe archival -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_deleting_property_follows_safe_convention(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $property = $this->propertyFor();
        $this->actingAs($admin)->post(
            route('admin.hotel.properties.images.store', $property, absolute: false),
            ['image' => UploadedFile::fake()->image('room.jpg')]
        )->assertRedirect();
        $path = $property->fresh()->images()->firstOrFail()->path;

        $this->actingAs($admin)->delete(
            route('admin.hotel.properties.destroy', $property, absolute: false)
        )->assertRedirect();

        // Soft-deleted, never hard-deleted; gallery rows and files stay
        // intact so a restore brings the listing back whole.
        $this->assertSoftDeleted('properties', ['id' => $property->id]);
        $this->assertSame(1, $property->images()->count());
        Storage::disk('public')->assertExists($path);
        $this->get(route('hotels.show', $property->slug, absolute: false))->assertNotFound();
    }
}
