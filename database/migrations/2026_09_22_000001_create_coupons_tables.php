<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 10: generic coupon / promo code foundation (CodeCanyon-ready).
     *
     * Pricing order (documented): tour base + add-ons = subtotal,
     * coupon discount → discounted subtotal, + tax (0 today) = total.
     * Commission is computed from the final discounted gross (coupon
     * reduces gross BEFORE commission). Platform-funded vs vendor-funded
     * coupons are intentionally deferred.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('minimum_booking_amount', 10, 2)->nullable();
            $table->decimal('maximum_discount_amount', 10, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->string('scope', 20)->default('global');
            $table->foreignId('vendor_profile_id')->nullable()->constrained('vendor_profiles')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'scope']);
            $table->index('vendor_profile_id');
        });

        Schema::create('coupon_tour_package', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('tour_package_id')->constrained('tour_packages')->cascadeOnDelete();
            $table->primary(['coupon_id', 'tour_package_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255)->nullable();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            $table->index('coupon_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_tour_package');
        Schema::dropIfExists('coupons');
    }
};
