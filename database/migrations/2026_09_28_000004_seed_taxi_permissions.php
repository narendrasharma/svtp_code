<?php

use App\Support\StaffPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $taxi = array_values(array_filter(
            StaffPermissions::all(),
            fn (string $name): bool => str_starts_with($name, 'taxi.')
        ));

        foreach ($taxi as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Grant to privileged staff roles only (operations precedent:
        // super-admin + administrator). Other roles gain taxi access
        // through the Roles UI or fresh seeds (defaultRoles).
        foreach (Role::whereIn('name', ['super-admin', 'administrator'])->get() as $role) {
            $current = $role->permissions()->pluck('name')->all();
            $valid = array_values(array_intersect(
                array_unique(array_merge($current, $taxi)),
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
