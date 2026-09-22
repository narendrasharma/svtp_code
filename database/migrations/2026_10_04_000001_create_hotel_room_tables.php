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
        Schema::create('hotel_bed_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hotel_room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_adults')->default(2);
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->unsignedTinyInteger('base_adults')->nullable();
            $table->unsignedTinyInteger('base_children')->nullable();
            $table->decimal('size_value', 8, 2)->nullable();
            $table->string('size_unit', 8)->nullable();
            $table->string('bed_summary', 255)->nullable();
            $table->string('inventory_mode', 12)->default('aggregate');
            $table->unsignedInteger('total_units')->nullable();
            $table->string('status', 12)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'slug'], 'room_type_property_slug_unique');
            $table->index(['property_id', 'status', 'sort_order'], 'room_type_property_status_idx');
            $table->index(['status', 'sort_order'], 'room_type_status_sort_idx');
        });

        Schema::create('hotel_room_type_beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
            $table->foreignId('bed_type_id')->constrained('hotel_bed_types')->restrictOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['room_type_id', 'bed_type_id'], 'room_type_bed_unique');
        });

        Schema::create('hotel_room_type_amenities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
            $table->foreignId('hotel_amenity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['room_type_id', 'hotel_amenity_id'], 'room_type_amenity_unique');
            $table->index('hotel_amenity_id');
        });

        Schema::create('hotel_room_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('alt_text', 150)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['room_type_id', 'sort_order']);
        });

        Schema::create('hotel_room_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
            $table->string('unit_name', 80);
            $table->string('floor', 40)->nullable();
            $table->string('status', 16)->default('active');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'unit_name'], 'room_unit_property_name_unique');
            $table->index(['room_type_id', 'status'], 'room_unit_type_status_idx');
            $table->index(['property_id', 'status'], 'room_unit_property_status_idx');
        });

        // Amenity scope extension (additive; existing rows stay property-level
        // except genuinely room-shared ones).
        Schema::table('hotel_amenities', function (Blueprint $table): void {
            $table->string('scope', 12)->default('property')->after('category');
        });

        DB::table('hotel_amenities')->whereIn('slug', ['free-wi-fi', 'air-conditioning'])->update(['scope' => 'both']);

        $now = now();

        foreach ([
            ['Single', 'single', 10], ['Double', 'double', 20], ['Queen', 'queen', 30],
            ['King', 'king', 40], ['Twin', 'twin', 50], ['Bunk Bed', 'bunk-bed', 60],
            ['Sofa Bed', 'sofa-bed', 70], ['Futon', 'futon', 80],
        ] as [$name, $slug, $order]) {
            DB::table('hotel_bed_types')->insertOrIgnore([
                'name' => $name, 'slug' => $slug, 'is_active' => true,
                'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            ['Television', 'television', 130], ['Minibar', 'minibar', 140],
            ['Balcony', 'balcony', 150], ['Work Desk', 'work-desk', 160],
            ['In-room Safe', 'in-room-safe', 170], ['Hair Dryer', 'hair-dryer', 180],
            ['Private Bathroom', 'private-bathroom', 190], ['Coffee Maker', 'coffee-maker', 200],
        ] as [$name, $slug, $order]) {
            DB::table('hotel_amenities')->insertOrIgnore([
                'name' => $name, 'slug' => $slug, 'category' => 'Room',
                'scope' => 'room', 'is_active' => true,
                'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            'hotel.room_types.view', 'hotel.room_types.manage',
            'hotel.room_units.view', 'hotel.room_units.manage',
            'hotel.bed_types.manage',
        ] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            foreach (DB::table('roles')->whereIn('name', ['administrator', 'super-admin'])->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_room_units');
        Schema::dropIfExists('hotel_room_images');
        Schema::dropIfExists('hotel_room_type_amenities');
        Schema::dropIfExists('hotel_room_type_beds');
        Schema::dropIfExists('hotel_room_types');
        Schema::dropIfExists('hotel_bed_types');
        Schema::table('hotel_amenities', function (Blueprint $table): void {
            $table->dropColumn('scope');
        });
    }
};
