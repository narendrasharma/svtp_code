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
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $permissions = ['taxi.pricing.view', 'taxi.pricing.manage'];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::whereIn('name', ['super-admin', 'administrator'])->get() as $role) {
            $current = $role->permissions()->pluck('name')->all();
            $valid = array_values(array_intersect(
                array_unique(array_merge($current, $permissions)),
                StaffPermissions::all()
            ));
            $role->syncPermissions($valid);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Permissions are append-only by design.
    }
};
