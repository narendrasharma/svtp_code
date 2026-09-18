<?php

use App\Support\StaffPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed the 11.5C support/communications/campaign permissions and attach
 * them additively to the default staff roles. Additive only
 * (givePermissionTo) — custom role edits made through the Roles UI are
 * never wiped.
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
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            $valid = array_values(array_intersect($definition['permissions'], StaffPermissions::all()));
            $role->givePermissionTo($valid);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permission catalogue is append-only; nothing to reverse.
    }
};
