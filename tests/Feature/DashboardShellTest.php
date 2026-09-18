<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DashboardShellTest extends TestCase
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

    protected function vendor(): User
    {
        $u = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $u->id]);

        return $u;
    }

    protected function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    public function test_vendor_layout_renders_one_profile_nav_link_only(): void
    {
        $content = file_get_contents(resource_path('js/Layouts/VendorLayout.vue'));
        // Navigation array should have exactly one Profile entry
        $this->assertEquals(1, substr_count($content, "'Profile', '/vendor/profile'"), 'Sidebar navigation should contain exactly one Profile link');
        // Total occurrences should be 2 (one nav + one topbar prop), not 3+ (duplicate)
        $this->assertEquals(2, substr_count($content, '/vendor/profile'), 'Should have nav + topbar only, no duplicate bottom link');
        // Ensure old duplicated sidebar bottom block is removed
        $this->assertStringNotContainsString('admin-account-summary', $content, 'Sidebar should not contain duplicated user/profile/logout block');
        $this->assertStringNotContainsString('SVTP Vendor', $content);
    }

    public function test_vendor_topbar_receives_effective_user(): void
    {
        $vendor = $this->vendor();
        $this->actingAs($vendor)->get(route('vendor.dashboard'))->assertOk()->assertInertia(fn ($page) => $page->where('auth.user.role', 'vendor')->where('auth.user.name', $vendor->name));
        $content = file_get_contents(resource_path('js/Components/Dashboard/DashboardUserMenu.vue'));
        $this->assertStringContainsString('page.props.auth', $content);
        $this->assertStringContainsString('userName', $content);
    }

    public function test_vendor_logout_remains_secure(): void
    {
        $vendor = $this->vendor();
        $this->actingAs($vendor)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
        // Ensure logout is POST only
        $this->actingAs($vendor)->get(route('logout'))->assertStatus(405);
    }

    public function test_vendor_profile_page_renders_readable_fields(): void
    {
        $vendor = $this->vendor();
        $this->actingAs($vendor)->get(route('vendor.profile.show'))->assertOk();
        $content = file_get_contents(resource_path('js/Pages/Vendor/Profile.vue'));
        // Check for high-contrast classes
        $this->assertStringContainsString('vendor-field-label', $content);
        $this->assertStringContainsString('vendor-field-value', $content);
        $this->assertStringContainsString('#94a3b8', $content); // label muted
        $this->assertStringContainsString('#f1f5f9', $content); // value high contrast
        $this->assertStringContainsString('vendor-card', $content);
        // Ensure no hardcoded SVTP
        $this->assertStringNotContainsString('SVTP Vendor', $content);
        $this->assertStringNotContainsString('SVTP Admin', file_get_contents(resource_path('js/Layouts/AdminLayout.vue')));
        $this->assertStringNotContainsString('Shree Vrindavan', $content);
    }

    public function test_vendor_profile_does_not_expose_sensitive_data(): void
    {
        $vendor = $this->vendor();
        $response = $this->actingAs($vendor)->get(route('vendor.profile.show'));
        $response->assertOk();
        $json = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString('storage_path', $json);
        $this->assertStringNotContainsString('password', strtolower($json));
    }

    public function test_admin_layout_renders_configured_site_identity(): void
    {
        $admin = $this->admin();
        Setting::setValue('site_name', 'Test Travel Platform');
        Setting::setValue('site_logo', 'logos/test-logo.png');
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $adminLayout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $this->assertStringContainsString('DashboardBrand', $adminLayout);
        $this->assertStringContainsString('panel-label', $adminLayout);
        $brand = file_get_contents(resource_path('js/Components/Dashboard/DashboardBrand.vue'));
        $this->assertStringContainsString('siteSettings', $brand);
        $this->assertStringContainsString('site_logo', $brand);
        $this->assertStringContainsString('site_name', $brand);
    }

    public function test_admin_topbar_dropdown_actions_appropriate(): void
    {
        $content = file_get_contents(resource_path('js/Components/Dashboard/DashboardUserMenu.vue'));
        $this->assertStringContainsString('Profile', $content);
        $this->assertStringContainsString('View Website', $content);
        $this->assertStringContainsString('Logout', $content);
        $this->assertStringContainsString("appUrl('/admin/logout')", $content);
        $this->assertStringContainsString('router.post', $content);
        // Check that AdminLayout passes profileUrl correctly
        $adminLayout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $this->assertStringContainsString("profile-url=\"appUrl('/admin/profile')\"", $adminLayout);
    }

    public function test_vendor_cannot_see_admin_navigation(): void
    {
        $vendor = $this->vendor();
        $this->actingAs($vendor)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($vendor)->get(route('admin.users.index'))->assertForbidden();
        $vendorLayout = file_get_contents(resource_path('js/Layouts/VendorLayout.vue'));
        $this->assertStringNotContainsString('/admin', $vendorLayout);
    }

    public function test_customer_cannot_see_admin_navigation(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        // Ensure AppLayout for customer does not contain admin links
        $appLayout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        // AppLayout is public, not admin, but ensure it doesn't expose admin dashboard to customer via isAdmin check
        $this->assertStringContainsString('isAdmin', $appLayout);
    }

    public function test_impersonated_vendor_shell_uses_effective_identity(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendor))->assertRedirect(route('vendor.dashboard'));
        $this->get(route('vendor.dashboard'))->assertOk()->assertInertia(fn ($page) => $page->where('auth.user.role', 'vendor')->where('auth.user.name', $vendor->name));
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_impersonated_customer_regression(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer))->assertRedirect(route('account.dashboard'));
        $this->get(route('account.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->post(route('impersonation.stop'))->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_configurable_logo_fallback(): void
    {
        Setting::setValue('site_name', 'Fallback Travel');
        Setting::setValue('site_logo', null);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $brand = file_get_contents(resource_path('js/Components/Dashboard/DashboardBrand.vue'));
        $this->assertStringContainsString('siteName', $brand);
        $this->assertStringContainsString('charAt(0)', $brand);
    }

    public function test_dashboard_layouts_work_under_code_base_path(): void
    {
        // Verify layouts use appUrl for base-path safety (JS will prepend /code)
        $vendorLayout = file_get_contents(resource_path('js/Layouts/VendorLayout.vue'));
        $this->assertStringContainsString('appUrl', $vendorLayout);
        $this->assertStringContainsString('/vendor', $vendorLayout);
        $adminLayout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $this->assertStringContainsString('appUrl', $adminLayout);
        $this->assertStringContainsString('/admin', $adminLayout);
        // Backend routes still work with normal host
        $vendor = $this->vendor();
        $this->actingAs($vendor)->get(route('vendor.dashboard'))->assertOk();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_admin_footer_exists(): void
    {
        $footer = file_get_contents(resource_path('js/Components/Dashboard/DashboardFooter.vue'));
        $this->assertStringContainsString('platformVersion', $footer);
        $this->assertStringContainsString('All rights reserved', $footer);
        $adminLayout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $this->assertStringContainsString('DashboardFooter', $adminLayout);
        $vendorLayout = file_get_contents(resource_path('js/Layouts/VendorLayout.vue'));
        $this->assertStringContainsString('DashboardFooter', $vendorLayout);
    }
}
