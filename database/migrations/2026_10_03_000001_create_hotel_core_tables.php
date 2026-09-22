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
        Schema::create('property_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hotel_amenities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('icon', 60)->nullable();
            $table->string('category', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->string('country_code', 2)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 255)->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('timezone', 60)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('children_policy', 255)->nullable();
            $table->string('pet_policy', 255)->nullable();
            $table->string('smoking_policy', 255)->nullable();
            $table->text('check_in_instructions')->nullable();
            $table->text('house_rules')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index(['vendor_profile_id', 'status']);
            $table->index(['property_type_id', 'status']);
            $table->index(['city_id', 'status']);
            $table->index(['star_rating', 'status']);
        });

        Schema::create('hotel_amenity_property', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_amenity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'hotel_amenity_id'], 'amenity_property_unique');
            $table->index('hotel_amenity_id');
        });

        Schema::create('property_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('alt_text', 150)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['property_id', 'sort_order']);
        });

        $now = now();

        foreach ([
            ['Hotel', 'hotel', 10], ['Resort', 'resort', 20], ['Hostel', 'hostel', 30],
            ['Guest House', 'guest-house', 40], ['Homestay', 'homestay', 50],
            ['Villa', 'villa', 60], ['Apartment', 'apartment', 70], ['Lodge', 'lodge', 80],
        ] as [$name, $slug, $order]) {
            DB::table('property_types')->insertOrIgnore([
                'name' => $name, 'slug' => $slug, 'is_active' => true,
                'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            ['Free Wi-Fi', 'free-wi-fi', 'General', 10], ['Parking', 'parking', 'Parking', 20],
            ['Restaurant', 'restaurant', 'Food & Drink', 30], ['Swimming Pool', 'swimming-pool', 'Wellness', 40],
            ['Air Conditioning', 'air-conditioning', 'General', 50], ['Room Service', 'room-service', 'Food & Drink', 60],
            ['Gym', 'gym', 'Wellness', 70], ['Spa', 'spa', 'Wellness', 80],
            ['Airport Shuttle', 'airport-shuttle', 'General', 90], ['Pet Friendly', 'pet-friendly', 'Family', 100],
            ['Wheelchair Access', 'wheelchair-access', 'Accessibility', 110], ['24-hour Front Desk', '24-hour-front-desk', 'General', 120],
        ] as [$name, $slug, $category, $order]) {
            DB::table('hotel_amenities')->insertOrIgnore([
                'name' => $name, 'slug' => $slug, 'category' => $category,
                'is_active' => true, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            'hotel.properties.view', 'hotel.properties.manage', 'hotel.properties.publish',
            'hotel.property_types.manage', 'hotel.amenities.manage', 'hotel.settings.manage',
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
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('hotel_amenity_property');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('hotel_amenities');
        Schema::dropIfExists('property_types');
    }
};
