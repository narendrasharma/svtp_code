<?php

namespace Tests\Feature\Platform;

use App\Models\Setting;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
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

    public function test_legacy_admin_sees_all_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $keys = $this->groupKeys(AdminNavigation::filteredFor($admin));

        foreach (['dashboard', 'tours', 'marketplace', 'bookings', 'finance', 'marketing', 'content', 'users', 'system'] as $expected) {
            $this->assertContains($expected, $keys);
        }
    }

    public function test_permission_filtering_limits_groups(): void
    {
        $content = $this->staffWithRole('content-manager');
        $groups = AdminNavigation::filteredFor($content);
        $keys = $this->groupKeys($groups);

        $this->assertContains('tours', $keys);
        $this->assertContains('content', $keys);
        $this->assertContains('marketing', $keys);
        $this->assertNotContains('finance', $keys);
        $this->assertNotContains('users', $keys);

        // Marketplace shows only tour-review moderation (tours.view);
        // vendor applications and plans stay hidden without vendors.view.
        $marketplace = collect($groups)->firstWhere('key', 'marketplace');
        $this->assertNotNull($marketplace);
        $this->assertSame(['Reviews'], collect($marketplace['items'])->pluck('label')->all());
    }

    public function test_module_filtering_removes_tours_group(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('modules.tours.enabled', '0');

        $keys = $this->groupKeys(AdminNavigation::filteredFor($admin));

        $this->assertNotContains('tours', $keys);
        $this->assertContains('dashboard', $keys);
        $this->assertContains('system', $keys);
    }

    public function test_no_duplicate_routes_or_labels(): void
    {
        $routes = collect(AdminNavigation::flatItems())->pluck('route')->all();
        $labels = collect(AdminNavigation::flatItems())->pluck('label')->all();

        // settings + seo-settings intentionally share the settings page;
        // everything else must be unique.
        $this->assertSame(count($routes), count(array_unique($routes)) + 1);
        $this->assertSame(count($labels), count(array_unique($labels)));
    }

    public function test_no_duplicate_profile_or_settings_links(): void
    {
        $labels = collect(AdminNavigation::flatItems())->pluck('label')->map(fn ($l) => mb_strtolower($l))->all();

        $this->assertContains('settings', $labels);
        $this->assertNotContains('profile', $labels);
        $this->assertSame(1, count(array_filter($labels, fn ($l) => $l === 'settings')));
    }

    public function test_active_group_logic(): void
    {
        $this->assertSame('system', AdminNavigation::activeGroupKey('/admin/settings'));
        $this->assertSame('system', AdminNavigation::activeGroupKey('/admin/settings?tab=seo'));
        $this->assertSame('tours', AdminNavigation::activeGroupKey('/admin/packages/5/edit'));
        $this->assertSame('bookings', AdminNavigation::activeGroupKey('/admin/bookings/12'));
        $this->assertSame('dashboard', AdminNavigation::activeGroupKey('/admin/dashboard'));
        $this->assertSame('users', AdminNavigation::activeGroupKey('/admin/staff/create'));
        $this->assertNull(AdminNavigation::activeGroupKey('/account/bookings'));
    }

    public function test_urls_are_root_relative_for_app_url_base_path(): void
    {
        // The /code subpath is prepended client-side via appUrl() (the
        // established base-path pattern, see DashboardShellTest), so the
        // registry resolves plain root-relative admin URLs.
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (AdminNavigation::filteredFor($admin) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertStringStartsWith('/admin/', $item['url'], "bad url for {$item['id']}");
            }
        }

        $sidebar = file_get_contents(resource_path('js/Components/Admin/AdminSidebar.vue'));
        $this->assertStringContainsString('appUrl(', $sidebar);

        $palette = file_get_contents(resource_path('js/Components/Admin/AdminCommandPalette.vue'));
        $this->assertStringContainsString('appUrl(', $palette);
    }

    public function test_sidebar_search_finds_seo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $matches = AdminNavigation::searchFiltered(AdminNavigation::filteredFor($admin), 'seo');

        $this->assertNotEmpty($matches);
        $labels = collect($matches)->pluck('label')->all();
        $this->assertContains('SEO Settings', $labels);
    }

    public function test_sidebar_search_matches_keywords_and_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $filtered = AdminNavigation::filteredFor($admin);

        $coupon = AdminNavigation::searchFiltered($filtered, 'coupon');
        $this->assertContains('Coupons', collect($coupon)->pluck('label')->all());

        $refund = AdminNavigation::searchFiltered($filtered, 'refund');
        $this->assertContains('All Bookings', collect($refund)->pluck('label')->all());
    }

    public function test_guests_and_customers_get_empty_navigation(): void
    {
        $this->assertSame([], AdminNavigation::filteredFor(null));

        $customer = User::factory()->create(['role' => 'customer']);
        $this->assertSame([], AdminNavigation::filteredFor($customer));
    }
}
