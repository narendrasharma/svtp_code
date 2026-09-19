<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Phase 12A.5: provider-neutral driver location telemetry. Raw
        // pings only — booking records stay authoritative and are never
        // derived from telemetry. Purged by ops:cleanup after the
        // configured retention window.
        Schema::create('taxi_driver_locations', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('taxi_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxi_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('accuracy_meters')->nullable();
            $table->decimal('heading', 5, 1)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->dateTime('captured_at');
            $table->dateTime('received_at');
            $table->string('source', 20)->default('driver_portal');
            $table->timestamps();

            $table->index(['driver_id', 'captured_at']);
            $table->index(['taxi_booking_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxi_driver_locations');
    }
};
