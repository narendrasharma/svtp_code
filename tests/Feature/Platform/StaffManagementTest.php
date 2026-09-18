<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function superAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin->fresh();
    }

    public function test_staff_index_lists_only_admin_accounts(): void
    {
        $super = $this->superAdmin();
        User::factory()->create(['role' => 'customer', 'name' => 'Just Customer']);
        User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($super)->get(route('admin.staff.index'));
        $response->assertOk();

        $names = collect($response->viewData('page')['props']['staff']['data'])->pluck('name')->all();
        $this->assertContains($super->name, $names);
        $this->assertNotContains('Just Customer', $names);
    }

    public function test_staff_search_filters_by_name(): void
    {
        $super = $this->superAdmin();
        User::factory()->create(['role' => 'admin', 'name' => 'Meera Operations']);
        User::factory()->create(['role' => 'admin', 'name' => 'Arjun Sales']);

        $response = $this->actingAs($super)->get(route('admin.staff.index', ['search' => 'Meera']));
        $names = collect($response->viewData('page')['props']['staff']['data'])->pluck('name')->all();

        $this->assertContains('Meera Operations', $names);
        $this->assertNotContains('Arjun Sales', $names);
    }

    public function test_super_admin_can_create_staff_with_roles(): void
    {
        $super = $this->superAdmin();

        $response = $this->actingAs($super)->post(route('admin.staff.store'), [
            'name' => 'New Executive',
            'email' => 'executive@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['booking-executive'],
        ]);

        $response->assertRedirect(route('admin.staff.index'));

        $member = User::where('email', 'executive@example.com')->firstOrFail();
        $this->assertSame('admin', $member->role);
        $this->assertTrue($member->hasSpatieRole('booking-executive'));
        $this->assertTrue($member->hasStaffPermission('bookings.create'));
        $this->assertFalse($member->hasStaffPermission('finance.view'));
    }

    public function test_staff_creation_never_creates_customer_or_vendor(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->post(route('admin.staff.store'), [
            'name' => 'Sneaky Vendor',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [],
            'role' => 'vendor',
        ]);

        $this->assertSame('admin', User::where('email', 'sneaky@example.com')->firstOrFail()->role);
    }

    public function test_staff_edit_shows_effective_permissions(): void
    {
        $super = $this->superAdmin();
        $member = User::factory()->create(['role' => 'admin']);
        $member->assignRole('finance-manager');

        $response = $this->actingAs($super)->get(route('admin.staff.edit', $member));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertContains('finance.view', $props['member']['effective_permissions']);
        $this->assertNotContains('content.pages', $props['member']['effective_permissions']);
    }

    public function test_role_crud(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->post(route('admin.roles.store'), [
            'name' => 'tour-editor',
            'description' => 'Edits tours only',
            'permissions' => ['dashboard.view', 'tours.view', 'tours.update'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'tour-editor')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'tours.view', 'tours.update'],
            $role->permissions()->pluck('name')->all()
        );

        $this->actingAs($super)->put(route('admin.roles.update', $role), [
            'name' => 'tour-editor',
            'description' => 'Edits tours and content',
            'permissions' => ['dashboard.view', 'tours.view', 'content.pages'],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'tours.view', 'content.pages'],
            $role->fresh()->permissions()->pluck('name')->all()
        );

        $this->actingAs($super)->delete(route('admin.roles.destroy', $role))->assertRedirect();
        $this->assertDatabaseMissing('roles', ['name' => 'tour-editor']);
    }

    public function test_role_permissions_reject_unknown_keys(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->post(route('admin.roles.store'), [
            'name' => 'hacker',
            'permissions' => ['server.destroy'],
        ])->assertInvalid('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'hacker']);
    }

    public function test_staff_management_requires_permission(): void
    {
        $viewer = User::factory()->create(['role' => 'admin']);
        $viewer->assignRole('support-agent');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($viewer->fresh())->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($viewer->fresh())->get(route('admin.staff.create'))->assertForbidden();
    }
}
