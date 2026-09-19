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
        // Phase 12A.4: driver visibility for operational notes. Internal by
        // default; driver-created notes are stored driver-visible.
        Schema::table('taxi_booking_notes', static function (Blueprint $table) {
            $table->boolean('visible_to_driver')->default(false)->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxi_booking_notes', static function (Blueprint $table) {
            $table->dropColumn('visible_to_driver');
        });
    }
};
