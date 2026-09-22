<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_room_inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
            $table->date('inventory_date');
            $table->unsignedInteger('capacity_override')->nullable();
            $table->unsignedInteger('blocked_units')->default(0);
            $table->boolean('stop_sell')->default(false);
            $table->string('note', 500)->nullable();
            $table->string('source', 16)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_room_type_id', 'inventory_date'], 'room_inventory_unique');
        });

        foreach (['hotel.inventory.view', 'hotel.inventory.manage'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            foreach (DB::table('roles')->whereIn('name', ['administrator', 'super-admin'])->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_room_inventories');
    }
};
