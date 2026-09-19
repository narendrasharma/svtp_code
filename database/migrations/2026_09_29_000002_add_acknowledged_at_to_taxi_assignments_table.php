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
        // Phase 12A.4: driver acknowledgement of an assignment. Server-owned
        // timestamp on the history row; never accepted from request input.
        Schema::table('taxi_assignments', static function (Blueprint $table) {
            $table->dateTime('acknowledged_at')->nullable()->after('unassigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxi_assignments', static function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }
};
