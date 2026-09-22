<?php

namespace Tests\Feature\Hotel;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * PRE-12B.9 critical admin action smoke test.
 *
 * Regression net for the class of bug where backend feature tests pass but
 * real browser usage 404s: the Vue admin UI addresses properties by numeric
 * ID (`/admin/hotel/properties/${property.id}/...`), so these tests assert
 * the RAW ID URLs the browser actually generates — not `route()` output.
 *
 * Goal is catching 404 / 405 / binding / permission / module-gating
 * regressions on high-value actions, not exhaustive business coverage.
 */
class HotelPropertyBrowserUrlSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Same base-URL normalization as HotelCoreTest: without this the
        // APP_URL subpath (/code) leaks into first-request route matching
        // in the test harness and every raw-URL assertion 404s for the
        // wrong reason. Production base handling is covered by appUrl().
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        app(ModuleManager::class)->setEnabled('hotels', true);
    }

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

    public function test_admin_property_edit_page_opens_with_browser_id_url(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Draft->value]);

        $this->actingAs($this->makeAdmin())
            ->get("/admin/hotel/properties/{$property->id}/edit")
            ->assertOk();
    }

    public function test_admin_property_show_page_opens_with_browser_id_url(): void
    {
        $property = Property::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->get("/admin/hotel/properties/{$property->id}")
            ->assertOk();
    }

    public function test_admin_gallery_upload_succeeds_with_browser_id_url(): void
    {
        Storage::fake('public');
        $property = Property::factory()->create();

        $response = $this->actingAs($this->makeAdmin())->post(
            "/admin/hotel/properties/{$property->id}/images",
            ['image' => UploadedFile::fake()->image('pool.jpg'), 'alt_text' => 'Pool side']
        );

        $response->assertRedirect();
        $image = $property->fresh()->images()->firstOrFail();
        $this->assertSame('Pool side', $image->alt_text);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_guest_gallery_upload_is_blocked_not_404_on_binding(): void
    {
        Storage::fake('public');
        $property = Property::factory()->create();

        // Guests are bounced to login (auth), proving the ID route resolves.
        $this->post(
            "/admin/hotel/properties/{$property->id}/images",
            ['image' => UploadedFile::fake()->image('pool.jpg')]
        )->assertRedirect();

        // Unknown IDs still 404.
        $this->actingAs($this->makeAdmin())->post(
            '/admin/hotel/properties/999999/images',
            ['image' => UploadedFile::fake()->image('pool.jpg')]
        )->assertNotFound();
    }

    public function test_admin_publish_succeeds_with_browser_id_url(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Draft->value]);

        $this->actingAs($this->makeAdmin())
            ->post("/admin/hotel/properties/{$property->id}/publish")
            ->assertRedirect();

        $fresh = $property->fresh();
        $this->assertSame(PropertyStatus::Published->value, $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_publish_unknown_id_returns_404(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post('/admin/hotel/properties/999999/publish')
            ->assertNotFound();
    }

    public function test_admin_room_types_nested_index_resolves_with_browser_id_url(): void
    {
        $property = Property::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->get("/admin/hotel/properties/{$property->id}/room-types")
            ->assertOk();
    }

    public function test_vendor_submit_succeeds_with_browser_id_url(): void
    {
        Setting::setValue('hotel.require_property_approval', '1');
        $vendor = $this->makeVendor();
        $property = Property::factory()->create([
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'status' => PropertyStatus::Draft->value,
        ]);

        $this->actingAs($vendor)
            ->post("/vendor/hotel/properties/{$property->id}/submit")
            ->assertRedirect();

        $this->assertSame(PropertyStatus::PendingReview->value, $property->fresh()->status);
    }

    public function test_hotel_module_toggle_gates_pages_and_reenables(): void
    {
        $admin = $this->makeAdmin();
        $property = Property::factory()->create(['status' => PropertyStatus::Draft->value]);

        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($admin)->get('/admin/hotel/properties')->assertNotFound();
        $this->actingAs($admin)->post("/admin/hotel/properties/{$property->id}/publish")->assertNotFound();

        app(ModuleManager::class)->setEnabled('hotels', true);

        $this->actingAs($admin)->get('/admin/hotel/properties')->assertOk();
        $this->actingAs($admin)->get(route('admin.modules.index', absolute: false))->assertOk();
        $this->actingAs($admin)->post("/admin/hotel/properties/{$property->id}/publish")->assertRedirect();
        $this->assertSame(PropertyStatus::Published->value, $property->fresh()->status);
    }
}
