<?php

namespace Tests\Feature\Platform;

use App\Models\Booking;
use App\Models\Page;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function staffWithRole(string $role): User
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff->fresh();
    }

    protected function groupKeys(array $groups): array
    {
        return collect($groups)->pluck('key')->all();
    }

    public function test_short_queries_return_empty(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'a']));
        $response->assertOk()->assertJsonPath('groups', []);
    }

    public function test_navigation_search_finds_seo(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'seo']));
        $response->assertOk();

        $navigation = collect($response->json('groups'))->firstWhere('key', 'navigation');
        $this->assertNotNull($navigation);
        $this->assertContains('Settings', collect($navigation['items'])->pluck('label')->all());
    }

    public function test_booking_reference_search(): void
    {
        $booking = Booking::factory()->create();
        $needle = substr($booking->booking_reference_id, 0, 10);

        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => $needle]));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'bookings');
        $this->assertNotNull($group);
        $this->assertContains($booking->booking_reference_id, collect($group['items'])->pluck('label')->all());
    }

    public function test_user_search(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Narendra Sharma']);

        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'Narendra']));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'users');
        $this->assertNotNull($group);
        $this->assertContains('Narendra Sharma', collect($group['items'])->pluck('label')->all());
    }

    public function test_vendor_search(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'business_name' => 'Braj Yatra Walas', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'Braj Yatra']));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'vendors');
        $this->assertNotNull($group);
        $this->assertContains('Braj Yatra Walas', collect($group['items'])->pluck('label')->all());
    }

    public function test_tour_search(): void
    {
        TourPackage::factory()->create(['title' => 'Mathura Vrindavan Darshan', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'Vrindavan Darshan']));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'tours');
        $this->assertNotNull($group);
        $this->assertContains('Mathura Vrindavan Darshan', collect($group['items'])->pluck('label')->all());
    }

    public function test_permission_filtering_hides_unauthorized_groups(): void
    {
        $booking = Booking::factory()->create();
        Page::factory()->create(['title' => 'Mathura Travel Guide']);

        $content = $this->staffWithRole('content-manager');
        $response = $this->actingAs($content)->getJson(route('admin.search', ['q' => substr($booking->booking_reference_id, 0, 10)]));

        $this->assertNotContains('bookings', $this->groupKeys($response->json('groups')));
        $this->assertNotContains('users', $this->groupKeys($response->json('groups')));

        $finance = $this->staffWithRole('finance-manager');
        $response = $this->actingAs($finance)->getJson(route('admin.search', ['q' => 'Mathura Travel']));

        $this->assertNotContains('pages', $this->groupKeys($response->json('groups')));
    }

    public function test_disabled_module_filters_search(): void
    {
        TourPackage::factory()->create(['title' => 'Mathura Vrindavan Darshan', 'is_active' => true]);
        Setting::setValue('modules.tours.enabled', '0');

        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'Vrindavan Darshan']));

        $this->assertNotContains('tours', $this->groupKeys($response->json('groups')));
    }

    public function test_sensitive_fields_are_absent(): void
    {
        Booking::factory()->create();
        User::factory()->create(['role' => 'customer', 'name' => 'Sensitive Probe']);

        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'Sensitive Probe']));
        $payload = json_encode($response->json());

        foreach (['password', 'remember_token', 'total_amount', 'commission', 'notification_preferences'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $payload);
        }

        // Items expose exactly the shaped keys.
        foreach ($response->json('groups') as $group) {
            foreach ($group['items'] as $item) {
                $this->assertEqualsCanonicalizing(['type', 'label', 'subtitle', 'url', 'icon'], array_keys($item));
            }
        }
    }

    public function test_result_urls_are_code_safe(): void
    {
        // Registry/search URLs are root-relative; the /code subpath is
        // prepended client-side via appUrl() (established base-path
        // pattern) — verified against the palette component below.
        Booking::factory()->create(['customer_name' => 'Codepath Check']);

        $response = $this->actingAs($this->admin())->getJson(route('admin.search', ['q' => 'Codepath Check']));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'bookings');
        $this->assertNotNull($group);

        foreach ($group['items'] as $item) {
            $this->assertStringStartsWith('/admin/bookings/', $item['url']);
        }

        $palette = file_get_contents(resource_path('js/Components/Admin/AdminCommandPalette.vue'));
        $this->assertStringContainsString('appUrl(item.url)', $palette);
    }

    public function test_guests_and_non_staff_cannot_search(): void
    {
        $this->getJson(route('admin.search', ['q' => 'test']))->assertUnauthorized();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $this->actingAs($vendor)->getJson(route('admin.search', ['q' => 'test']))->assertForbidden();
    }
}
