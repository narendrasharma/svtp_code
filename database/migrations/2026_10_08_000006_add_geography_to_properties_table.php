<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared geography foundation (12B.4.1): structured Property geography.
     *
     * Additive only — city_id/state_id, the legacy country_code text and
     * every address/coordinate column are preserved. Properties gain an
     * optional Country relation and an optional primary
     * Destination/area. A property with a valid City but no destination
     * stays perfectly valid; the destination is merchandising context.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('state_id')->constrained()->nullOnDelete();
            $table->foreignId('destination_id')->nullable()->after('country_id')->constrained()->nullOnDelete();

            $table->index(['country_id']);
            $table->index(['destination_id']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['country_id']);
            $table->dropIndex(['destination_id']);
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('destination_id');
        });
    }
};
