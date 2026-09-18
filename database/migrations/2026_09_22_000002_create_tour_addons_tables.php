<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 10: tour add-ons with immutable booking snapshots.
     *
     * bookings.addons_total + coupon snapshot columns. Historical rows keep
     * addons_total = 0 and null coupon fields (backward compatible:
     * subtotal already equals the tour base for those rows).
     */
    public function up(): void
    {
        Schema::create('tour_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained('tour_packages')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('pricing_type', 20);
            $table->decimal('price', 10, 2);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tour_package_id', 'is_active']);
        });

        Schema::create('booking_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('tour_addon_id')->nullable()->constrained('tour_addons')->nullOnDelete();
            $table->string('name', 255);
            $table->string('pricing_type', 20);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();

            $table->index('booking_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('discount_amount')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 50)->nullable()->after('coupon_id');
            $table->string('coupon_discount_type', 20)->nullable()->after('coupon_code');
            $table->decimal('coupon_discount_value', 10, 2)->nullable()->after('coupon_discount_type');
            $table->decimal('addons_total', 10, 2)->default(0)->after('coupon_discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'coupon_id',
                'coupon_code',
                'coupon_discount_type',
                'coupon_discount_value',
                'addons_total',
            ]);
        });

        Schema::dropIfExists('booking_addons');
        Schema::dropIfExists('tour_addons');
    }
};
