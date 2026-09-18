<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorProfile;
use App\Models\VendorVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminUsersTest extends TestCase
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

    protected function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer'], $overrides));
    }

    protected function vendor(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'vendor'], $overrides));
        VendorProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return $user;
    }

    public function test_admin_users_index_requires_admin(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->actingAs($this->customer())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->vendor())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk();
    }

    public function test_all_roles_appear(): void
    {
        $admin = $this->admin();
        $customer = $this->customer(['name' => 'Customer One']);
        $vendor = $this->vendor();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('users.data', 3)
        );
        // Check that all three roles are in the response data
        $users = $response->viewData('page')['props']['users']['data'];
        $roles = collect($users)->pluck('role')->sort()->values()->all();
        $this->assertContains('admin', $roles);
        $this->assertContains('customer', $roles);
        $this->assertContains('vendor', $roles);
    }

    public function test_search_by_name_email_phone(): void
    {
        $admin = $this->admin();
        $c1 = $this->customer(['name' => 'Alice Wonderland', 'email' => 'alice@example.com', 'phone' => '1111111111']);
        $c2 = $this->customer(['name' => 'Bob Builder', 'email' => 'bob@example.com', 'phone' => '2222222222']);

        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Alice']))->assertInertia(fn ($page) => $page->has('users.data', 1));
        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'bob@example.com']))->assertInertia(fn ($page) => $page->has('users.data', 1));
        $this->actingAs($admin)->get(route('admin.users.index', ['search' => '2222222222']))->assertInertia(fn ($page) => $page->has('users.data', 1));
        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'nonexistent']))->assertInertia(fn ($page) => $page->has('users.data', 0));
    }

    public function test_role_filter(): void
    {
        $admin = $this->admin();
        $this->customer();
        $this->customer();
        $this->vendor();

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'customer']))->assertInertia(fn ($page) => $page->has('users.data', 2));
        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'vendor']))->assertInertia(fn ($page) => $page->has('users.data', 1));
        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'admin']))->assertInertia(fn ($page) => $page->has('users.data', 1));
    }

    public function test_pagination_remains_filtered(): void
    {
        $admin = $this->admin();
        // Create 20 customers
        User::factory()->count(20)->create(['role' => 'customer']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'customer', 'per_page' => 10]));
        $response->assertOk();
        $users = $response->viewData('page')['props']['users'];
        $this->assertEquals(10, count($users['data']));
        // Check that pagination links preserve query string (withQueryString)
        $this->assertStringContainsString('role=customer', $users['links'][1]['url'] ?? '');
    }

    public function test_detail_page_works(): void
    {
        $admin = $this->admin();
        $customer = $this->customer(['name' => 'Detail Customer']);
        $this->actingAs($admin)->get(route('admin.users.show', $customer))->assertOk()->assertInertia(fn ($page) => $page->where('user.name', 'Detail Customer'));
    }

    public function test_customer_relation_booking_summary_scoped(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $other = $this->customer();
        $b1 = Booking::factory()->create(['user_id' => $customer->id, 'customer_name' => 'Customer Booking']);
        Booking::factory()->create(['user_id' => $other->id, 'customer_name' => 'Other Booking']);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $customer));
        $response->assertOk();
        $recent = $response->viewData('page')['props']['recentBookings'];
        $this->assertCount(1, $recent);
        $this->assertEquals($b1->booking_reference_id, $recent[0]['booking_reference_id']);
        // Ensure other user's booking not leaked
        $this->assertCount(1, $recent);
    }

    public function test_vendor_relations_load_correctly(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $app = VendorApplication::factory()->create(['user_id' => $vendor->id, 'status' => 'approved', 'business_name' => 'Vendor Biz']);
        $ver = VendorVerification::factory()->create(['user_id' => $vendor->id, 'vendor_application_id' => $app->id, 'status' => 'verified']);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $vendor));
        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertEquals('Vendor Biz', $props['vendorApplication']['business_name']);
        $this->assertNotNull($props['vendorProfile']);
        $this->assertEquals('verified', $props['kycStatus']);
    }

    public function test_sensitive_fields_never_exposed(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $content = json_encode($response->viewData('page')['props']['users']['data']);
        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('vendor_kyc', $content);

        $detail = $this->actingAs($admin)->get(route('admin.users.show', $customer));
        $detailContent = json_encode($detail->viewData('page')['props']['user']);
        $this->assertStringNotContainsString('password', strtolower($detailContent));
    }

    public function test_customer_impersonation_works(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer))->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_approved_vendor_impersonation_works(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendor))->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($vendor);
    }

    public function test_admin_self_impersonation_unavailable(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $admin))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $otherAdmin))->assertForbidden();
    }

    public function test_vendor_role_cannot_be_granted_via_ordinary_update(): void
    {
        // No user update route should allow role escalation
        $customer = $this->customer();
        $admin = $this->admin();
        // Try to patch user via any existing route (none should exist for role) — should be 404 or 405
        $response = $this->actingAs($admin)->patch("/admin/users/{$customer->id}", ['role' => 'vendor']);
        $this->assertTrue(in_array($response->getStatusCode(), [404, 405]));
        $this->actingAs($customer)->patch('/account/profile', ['name' => 'Hacked', 'email' => $customer->email, 'role' => 'vendor']);
        $customer->refresh();
        $this->assertEquals('customer', $customer->role);
        $this->assertEquals('Hacked', $customer->name);
    }

    public function test_existing_registration_role_security_remains(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'sneaky2@example.com', 'role' => 'customer']);
    }

    public function test_users_index_pagination_per_page_options(): void
    {
        $admin = $this->admin();
        User::factory()->count(30)->create(['role' => 'customer']);
        $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 25]))->assertOk()->assertInertia(fn ($page) => $page->has('users.data', 25));
        $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 50]))->assertOk();
    }
}
