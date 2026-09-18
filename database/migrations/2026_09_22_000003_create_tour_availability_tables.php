<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 10: practical tour availability foundation.
     *
     * Defaults preserve current behavior: booking enabled, all weekdays
     * allowed (null), no advance restrictions, no blackout rows — every
     * existing tour stays bookable after this migration.
     *
     * Capacity / seats inventory is intentionally deferred (no clean
     * inventory model exists today; see TourAvailabilityService).
     */
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->boolean('booking_enabled')->default(true)->after('is_active');
            $table->json('available_weekdays')->nullable()->after('booking_enabled');
            $table->unsignedInteger('min_advance_days')->default(0)->after('available_weekdays');
            $table->unsignedInteger('max_advance_days')->nullable()->after('min_advance_days');
        });

        Schema::create('tour_blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained('tour_packages')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tour_package_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_blackout_dates');

        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn([
                'booking_enabled',
                'available_weekdays',
                'min_advance_days',
                'max_advance_days',
            ]);
        });
    }
};
