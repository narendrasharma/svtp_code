<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 12A.3: internal operational notes for dispatch desks.
        // Never serialized to customer surfaces; admin sees all, vendor
        // sees own bookings only (enforced in controllers).
        Schema::create('taxi_booking_notes', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('body', 2000);
            $table->timestamps();

            $table->index('taxi_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxi_booking_notes');
    }
};
