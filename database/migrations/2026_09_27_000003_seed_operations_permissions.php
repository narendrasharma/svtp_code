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

        $new = ['analytics.view', 'audit.view', 'system.health.view', 'system.jobs.manage'];

        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Grant operational permissions to privileged staff roles only.
        // Never blanket-grant system.jobs.manage / audit.view.
        foreach (Role::whereIn('name', ['super-admin', 'administrator'])->get() as $role) {
            $current = $role->permissions()->pluck('name')->all();
            $valid = array_values(array_intersect(
                array_unique(array_merge($current, $new)),
                StaffPermissions::all()
            ));
            $role->syncPermissions($valid);
        }
    }

    public function down(): void
    {
        // Permissions are append-only by design; do not delete on rollback
        // to avoid stripping grants made after this migration.
    }
};
