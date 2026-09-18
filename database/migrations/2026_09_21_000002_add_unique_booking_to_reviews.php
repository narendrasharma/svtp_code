<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9: one verified review per booking.
     *
     * Nullable booking_id keeps guest reviews working (multiple NULLs are
     * allowed by the unique index on both MySQL and SQLite) while a booking
     * can never collect two verified reviews.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['booking_id']);
        });
    }
};
