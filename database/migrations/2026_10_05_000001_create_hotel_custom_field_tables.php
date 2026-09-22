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
        Schema::create('hotel_custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 16);
            $table->string('name', 100);
            $table->string('key', 80);
            $table->string('field_type', 16);
            $table->string('group_name', 80)->nullable();
            $table->string('help_text', 500)->nullable();
            $table->string('placeholder', 150)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_frontend')->default(true);
            $table->boolean('show_label')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_type', 'key'], 'custom_field_entity_key_unique');
            $table->index(['entity_type', 'is_active', 'sort_order'], 'custom_field_entity_active_idx');
        });

        Schema::create('hotel_custom_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('definition_id')->constrained('hotel_custom_field_definitions')->cascadeOnDelete();
            $table->string('entity_type', 16);
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'entity_type', 'entity_id'], 'custom_value_unique');
            $table->index(['entity_type', 'entity_id'], 'custom_value_entity_idx');
        });

        Schema::create('hotel_custom_field_property_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('definition_id')->constrained('hotel_custom_field_definitions')->cascadeOnDelete();
            $table->foreignId('property_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['definition_id', 'property_type_id'], 'custom_field_type_unique');
            $table->index('property_type_id');
        });

        foreach (['hotel.custom_fields.view', 'hotel.custom_fields.manage'] as $name) {
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
        Schema::dropIfExists('hotel_custom_field_property_type');
        Schema::dropIfExists('hotel_custom_field_values');
        Schema::dropIfExists('hotel_custom_field_definitions');
    }
};
