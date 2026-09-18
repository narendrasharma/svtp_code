<?php

namespace Tests\Feature\Platform;

use App\Models\Booking;
use App\Models\User;
use App\Models\VendorProfile;
use App\Support\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'admin'], $overrides));
    }

    protected function staffWithRole(string $role): User
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff->fresh();
    }

    public function test_existing_admin_retains_full_access_after_migration(): void
    {
        // Legacy admin: role=admin, zero staff roles — compatibility path.
        $admin = $this->admin();

        $this->assertFalse($admin->hasStaffRoles());
        $this->assertTrue($admin->hasStaffPermission('finance.withdrawals'));
        $this->assertTrue($admin->hasStaffPermission('roles.manage'));

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.withdrawals.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.pages.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
    }

    public function test_super_admin_has_every_permission(): void
    {
        $super = $this->staffWithRole('super-admin');

        $this->assertTrue($super->isSuperAdmin());

        foreach (StaffPermissions::all() as $permission) {
            $this->assertTrue($super->hasStaffPermission($permission), "missing {$permission}");
        }

        $this->actingAs($super)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($super)->get(route('admin.modules.index'))->assertOk();
    }

    public function test_content_manager_cannot_access_finance(): void
    {
        $staff = $this->staffWithRole('content-manager');
        $booking = Booking::factory()->create();

        $this->actingAs($staff)->get(route('admin.pages.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.packages.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.withdrawals.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.payout-accounts.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.bookings.refunds.store', $booking))->assertForbidden();
    }

    public function test_finance_manager_cannot_edit_content(): void
    {
        $staff = $this->staffWithRole('finance-manager');

        $this->actingAs($staff)->get(route('admin.withdrawals.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.pages.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.menus.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.packages.create'))->assertForbidden();
    }

    public function test_vendor_and_customer_are_unaffected_by_staff_rbac(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->assertFalse($vendor->hasStaffPermission('tours.view'));
        $this->assertFalse($customer->hasStaffPermission('dashboard.view'));

        $this->actingAs($vendor)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        // Vendor's own area still works.
        $this->actingAs($vendor)->get(route('vendor.dashboard'))->assertOk();
    }

    public function test_non_authorized_staff_cannot_manage_roles(): void
    {
        // administrator role excludes roles.manage by seed design.
        $staff = $this->staffWithRole('administrator');

        $this->actingAs($staff)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.roles.store'), [
            'name' => 'rogue',
            'permissions' => ['dashboard.view'],
        ])->assertForbidden();
    }

    public function test_staff_cannot_self_elevate_to_super_admin(): void
    {
        $staff = $this->staffWithRole('administrator');

        $response = $this->actingAs($staff)->post(route('admin.staff.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['super-admin'],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_super_admin_cannot_remove_own_super_admin(): void
    {
        $super = $this->staffWithRole('super-admin');

        $response = $this->actingAs($super)->put(route('admin.staff.update', $super), [
            'name' => $super->name,
            'email' => $super->email,
            'roles' => ['administrator'],
        ]);

        $response->assertForbidden();
        $this->assertTrue($super->fresh()->isSuperAdmin());
    }

    public function test_protected_super_admin_role_cannot_be_deleted(): void
    {
        $super = $this->staffWithRole('super-admin');
        $role = Role::where('name', 'super-admin')->firstOrFail();

        $this->actingAs($super)
            ->delete(route('admin.roles.destroy', $role))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
    }

    public function test_impersonation_requires_explicit_permission(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $without = $this->staffWithRole('content-manager');
        $with = $this->staffWithRole('support-agent');

        $this->actingAs($without)
            ->post(route('admin.users.impersonate', $customer))
            ->assertForbidden();

        $this->actingAs($with)
            ->post(route('admin.users.impersonate', $customer))
            ->assertRedirect(route('account.dashboard'));
    }

    public function test_impersonated_user_has_no_hidden_staff_privileges(): void
    {
        $support = $this->staffWithRole('support-agent');
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($support)->post(route('admin.users.impersonate', $customer));

        // Effective user is the customer: admin area is forbidden even
        // though the originating staff member had staff permissions.
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.search', ['q' => 'test']))->assertForbidden();
    }
}
