<?php

use App\Models\User;
use App\Support\StaffPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Seed staff RBAC catalogue + migrate existing admins (Phase 11.5A).
 *
 * Compatibility strategy (no manual DB repair, nobody locked out):
 *  - every permission in StaffPermissions is created (web guard);
 *  - every default role in StaffPermissions::defaultRoles() is created
 *    with its permission set (super-admin is flagged protected);
 *  - every EXISTING users.role=admin account receives the
 *    `administrator` staff role (equivalent operational access);
 *  - the oldest existing admin additionally receives `super-admin`
 *    so the platform always has a super admin after migration.
 *
 * Forward-only data: re-running is idempotent (firstOrCreate + sync).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (StaffPermissions::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (StaffPermissions::defaultRoles() as $roleName => $definition) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'description' => $definition['description'] ?? null,
                    'is_protected' => (bool) ($definition['protected'] ?? false),
                ]
            );

            $valid = array_values(array_intersect($definition['permissions'], StaffPermissions::all()));
            $role->syncPermissions($valid);
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        $adminIds = User::where('role', 'admin')->orderBy('id')->pluck('id')->all();

        if ($adminIds === []) {
            return;
        }

        $administrator = Role::where('name', StaffPermissions::ADMINISTRATOR_ROLE)->first();
        $superAdmin = Role::where('name', StaffPermissions::SUPER_ADMIN_ROLE)->first();

        foreach ($adminIds as $adminId) {
            $admin = User::find($adminId);

            if (! $admin) {
                continue;
            }

            if ($administrator && ! $admin->hasSpatieRole($administrator->name)) {
                $admin->assignRole($administrator);
            }
        }

        // Oldest admin becomes the initial super admin.
        $firstAdmin = User::find($adminIds[0]);

        if ($firstAdmin && $superAdmin && ! $firstAdmin->hasSpatieRole($superAdmin->name)) {
            $firstAdmin->assignRole($superAdmin);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Data-only migration: nothing to reverse structurally.
    }
};
