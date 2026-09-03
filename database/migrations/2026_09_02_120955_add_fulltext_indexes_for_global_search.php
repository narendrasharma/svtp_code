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
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('tour_packages', function (Blueprint $table) {
            $table->fullText(
                ['title', 'overview', 'meta_description'],
                'tour_packages_search_fulltext'
            );
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->fullText(
                ['name', 'description', 'meta_description'],
                'destinations_search_fulltext'
            );
        });

        Schema::table('places', function (Blueprint $table) {
            $table->fullText(
                ['name', 'description', 'meta_description'],
                'places_search_fulltext'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropFullText('tour_packages_search_fulltext');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->dropFullText('destinations_search_fulltext');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropFullText('places_search_fulltext');
        });
    }
};
