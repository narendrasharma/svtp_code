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
        // Phase 12A.8: controlled auto-dispatch offer history. One row
        // per attempt; rows move pending → terminal only.
        Schema::create('taxi_dispatch_offers', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('taxi_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rank')->default(0);
            $table->string('status', 20)->default('pending');
            $table->dateTime('offered_at');
            $table->dateTime('expires_at');
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->string('response_reason', 30)->nullable();
            $table->string('source', 20)->default('auto');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['taxi_booking_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxi_dispatch_offers');
    }
};
