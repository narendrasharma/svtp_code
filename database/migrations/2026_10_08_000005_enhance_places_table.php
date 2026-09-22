<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared geography foundation (12B.4.1): Place/attraction discovery
     * flags. Additive only — destination links, names, slugs, images and
     * SEO columns are preserved. Places gain an active flag (public pages
     * must be hideable without deleting), sort order and coordinates.
     */
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('image');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('is_active')->default(true)->after('longitude');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');

            $table->index(['destination_id', 'is_active']);
            $table->index(['sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['destination_id', 'is_active']);
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['latitude', 'longitude', 'is_active', 'sort_order']);
        });
    }
};
