<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 13A: shared platform language registry. Module-independent.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique()->comment('BCP-47-ish code, e.g. en, hi, ar');
            $table->string('locale', 12)->unique()->comment('Runtime locale, e.g. en, hi, ar');
            $table->string('name', 100)->comment('English display name, e.g. English');
            $table->string('native_name', 100)->comment('Native display name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_rtl')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('date_format', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
