<?php

use App\Support\StaffPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Shared geography foundation (12B.4.1): seed the locations.*
     * permission catalogue.
     *
     * Follows the taxi-permissions precedent: create every locations.*
     * key, then grant the set to the privileged staff roles
     * (super-admin + administrator). Other roles gain geography access
     * through the Roles UI or fresh seeds (defaultRoles). Append-only:
     * down() intentionally does nothing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $locations = array_values(array_filter(
            StaffPermissions::all(),
            fn (string $name): bool => str_starts_with($name, 'locations.')
        ));

        foreach ($locations as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::whereIn('name', ['super-admin', 'administrator'])->get() as $role) {
            $current = $role->permissions()->pluck('name')->all();
            $valid = array_values(array_intersect(
                array_unique(array_merge($current, $locations)),
                StaffPermissions::all()
            ));
            $role->syncPermissions($valid);
        }
    }

    public function down(): void
    {
        // Permissions are append-only by design.
    }
};
