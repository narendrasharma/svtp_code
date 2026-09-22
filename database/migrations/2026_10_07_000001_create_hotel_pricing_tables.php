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
        Schema::create('hotel_rate_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 60);
            $table->string('description', 500)->nullable();
            $table->string('currency', 3);
            $table->string('meal_plan', 20)->default('room_only');
            $table->string('cancellation_mode', 20)->default('flexible');
            $table->string('cancellation_note', 500)->nullable();
            $table->unsignedTinyInteger('base_adults')->default(2);
            $table->unsignedTinyInteger('base_children')->default(0);
            $table->decimal('base_rate', 12, 2);
            $table->decimal('extra_adult_rate', 12, 2)->default(0);
            $table->decimal('extra_child_rate', 12, 2)->default(0);
            $table->unsignedSmallInteger('minimum_stay')->nullable();
            $table->unsignedSmallInteger('maximum_stay')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'code'], 'rate_plan_property_code_unique');
            $table->index(['hotel_room_type_id', 'is_active', 'sort_order'], 'rate_plan_room_active_idx');
        });

        Schema::create('hotel_daily_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_rate_plan_id')->constrained('hotel_rate_plans')->cascadeOnDelete();
            $table->date('rate_date');
            $table->decimal('amount_override', 12, 2)->nullable();
            $table->decimal('extra_adult_override', 12, 2)->nullable();
            $table->decimal('extra_child_override', 12, 2)->nullable();
            $table->unsignedSmallInteger('minimum_stay_override')->nullable();
            $table->unsignedSmallInteger('maximum_stay_override')->nullable();
            $table->boolean('stop_sell')->default(false);
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->string('note', 500)->nullable();
            $table->string('source', 16)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_rate_plan_id', 'rate_date'], 'daily_rate_unique');
        });

        Schema::create('hotel_rate_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_rate_plan_id')->constrained('hotel_rate_plans')->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('adjustment_type', 20);
            $table->decimal('adjustment_value', 12, 2);
            $table->integer('priority')->default(0);
            $table->json('applicable_weekdays')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_rate_plan_id', 'is_active'], 'rate_season_plan_active_idx');
        });

        Schema::create('hotel_charge_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('charge_type', 12);
            $table->string('calculation', 20);
            $table->decimal('value', 12, 2);
            $table->string('currency', 3);
            $table->boolean('included_in_price')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['property_id', 'is_active', 'sort_order'], 'charge_rule_property_idx');
        });

        foreach (['hotel.rate_plans.view', 'hotel.rate_plans.manage', 'hotel.pricing.view', 'hotel.pricing.manage', 'hotel.charges.manage'] as $name) {
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
        Schema::dropIfExists('hotel_charge_rules');
        Schema::dropIfExists('hotel_rate_seasons');
        Schema::dropIfExists('hotel_daily_rates');
        Schema::dropIfExists('hotel_rate_plans');
    }
};
