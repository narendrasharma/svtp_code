<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared geography foundation (12B.4.1): Destination becomes the
     * travel/discovery entity (city, area, region, island, tourism zone).
     *
     * Additive only — ids, names, slugs, city links, images, descriptions
     * and SEO columns are preserved. New nullable geography context
     * (country/state/city), optional hierarchy (parent_id), controlled
     * destination_type and merchandising flags (featured/sort/coords).
     */
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('city_id')->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('state_id')->constrained('destinations')->nullOnDelete();
            $table->string('destination_type', 30)->nullable()->after('parent_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('image');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');

            $table->index(['country_id', 'is_active']);
            $table->index(['state_id', 'is_active']);
            $table->index(['city_id', 'is_active']);
            $table->index(['parent_id']);
            $table->index(['is_featured', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropIndex(['country_id', 'is_active']);
            $table->dropIndex(['state_id', 'is_active']);
            $table->dropIndex(['city_id', 'is_active']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_featured', 'sort_order']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn([
                'destination_type', 'latitude', 'longitude',
                'is_featured', 'sort_order',
            ]);
        });
    }
};
