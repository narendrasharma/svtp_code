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
        Schema::table('places', function (Blueprint $table) {
            $table->string('excerpt', 500)->nullable()->after('slug');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->string('excerpt', 500)->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });
    }
};
