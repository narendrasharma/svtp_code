<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9: minimal notification preference foundation.
     *
     * Nullable JSON map, e.g. {"booking": false}. Absent keys default to
     * opted-in. Mandatory transactional/security mail bypasses prefs in
     * code; no preference UI ships in this phase (deferred).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
