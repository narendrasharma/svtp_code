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
            $table->text('description')->nullable()->after('slug');
            $table->string('image')->nullable()->after('description');
            $table->string('meta_description')->nullable()->after('image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['description', 'image', 'meta_description']);
        });
    }
};
