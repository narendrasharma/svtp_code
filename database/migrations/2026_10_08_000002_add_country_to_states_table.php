<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared geography foundation (12B.4.1): attach states to countries.
     *
     * Additive only — the existing `states` table keeps its name, ids and
     * slugs. country_id stays nullable so legacy rows without a verified
     * country keep working until an admin remediates them.
     */
    public function up(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('code', 10)->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('slug');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');

            $table->index(['country_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropIndex(['country_id', 'is_active']);
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn(['code', 'is_active', 'sort_order']);
        });
    }
};
