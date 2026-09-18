<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 11: generic vendor plans + feature entitlements + assignment
     * history. No billing — admin assigns manually; architecture allows
     * Stripe/Razorpay subscriptions later via assignments (starts/ends).
     *
     * Limits: value_type integer|boolean|string|unlimited (never magic
     * negatives). Downgrades never delete content — enforcement blocks
     * only new/activating actions.
     */
    public function up(): void
    {
        Schema::create('vendor_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('vendor_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_plan_id')->constrained('vendor_plans')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label', 255)->nullable();
            $table->string('value_type', 20);
            $table->integer('integer_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->string('string_value', 100)->nullable();
            $table->timestamps();

            $table->unique(['vendor_plan_id', 'key']);
        });

        Schema::create('vendor_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('vendor_plan_id')->constrained('vendor_plans')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['vendor_profile_id', 'starts_at']);
        });

        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->foreignId('vendor_plan_id')->nullable()->after('verification_status')->constrained('vendor_plans')->nullOnDelete();
        });

        $this->seedDefaultPlans();
        $this->backfillExistingVendors();
    }

    protected function seedDefaultPlans(): void
    {
        $now = now()->toDateTimeString();

        $starterId = DB::table('vendor_plans')->insertGetId([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Default plan for approved vendors.',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $growthId = DB::table('vendor_plans')->insertGetId([
            'name' => 'Growth',
            'slug' => 'growth',
            'description' => 'Higher limits plus featured listings and advanced analytics.',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $features = [
            // Starter: sensible defaults for a new vendor.
            [$starterId, 'max_active_tours', 'Max active tours', 'integer', 10, null, null],
            [$starterId, 'max_coupons', 'Max coupons', 'integer', 5, null, null],
            [$starterId, 'max_addons_per_tour', 'Max add-ons per tour', 'integer', 5, null, null],
            [$starterId, 'featured_listing', 'Featured listing', 'boolean', null, false, null],
            [$starterId, 'storefront_enabled', 'Public storefront', 'boolean', null, true, null],
            [$starterId, 'analytics_level', 'Analytics level', 'string', null, null, 'basic'],
            // Growth: room to scale.
            [$growthId, 'max_active_tours', 'Max active tours', 'integer', 50, null, null],
            [$growthId, 'max_coupons', 'Max coupons', 'integer', 20, null, null],
            [$growthId, 'max_addons_per_tour', 'Max add-ons per tour', 'integer', 10, null, null],
            [$growthId, 'featured_listing', 'Featured listing', 'boolean', null, true, null],
            [$growthId, 'storefront_enabled', 'Public storefront', 'boolean', null, true, null],
            [$growthId, 'analytics_level', 'Analytics level', 'string', null, null, 'advanced'],
        ];

        foreach ($features as [$planId, $key, $label, $type, $int, $bool, $str]) {
            DB::table('vendor_plan_features')->insert([
                'vendor_plan_id' => $planId,
                'key' => $key,
                'label' => $label,
                'value_type' => $type,
                'integer_value' => $int,
                'boolean_value' => $bool,
                'string_value' => $str,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function backfillExistingVendors(): void
    {
        $defaultId = DB::table('vendor_plans')->where('slug', 'starter')->value('id');

        if (! $defaultId) {
            return;
        }

        $now = now()->toDateTimeString();

        DB::table('vendor_profiles')->whereNull('vendor_plan_id')->orderBy('id')->chunkById(200, function ($profiles) use ($defaultId, $now): void {
            foreach ($profiles as $profile) {
                DB::table('vendor_profiles')->where('id', $profile->id)->update(['vendor_plan_id' => $defaultId]);
                DB::table('vendor_plan_assignments')->insert([
                    'vendor_profile_id' => $profile->id,
                    'vendor_plan_id' => $defaultId,
                    'starts_at' => $now,
                    'ends_at' => null,
                    'assigned_by' => null,
                    'note' => 'Phase 11 backfill: default plan.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropForeign(['vendor_plan_id']);
            $table->dropColumn('vendor_plan_id');
        });

        Schema::dropIfExists('vendor_plan_assignments');
        Schema::dropIfExists('vendor_plan_features');
        Schema::dropIfExists('vendor_plans');
    }
};
