<?php

use Illuminate\Database\Migrations\Migration;
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
        $names = ['hotel.reviews.view', 'hotel.reviews.moderate', 'hotel.reviews.reply'];

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Role::whereIn('name', ['super-admin', 'administrator'])->get() as $role) {
            $role->givePermissionTo($names);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Permissions are append-only, matching the platform permission seeds.
    }
};
