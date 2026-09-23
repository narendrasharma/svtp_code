<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 13B: shared platform currency registry. Module-independent.
     * Authoritative domain prices (Hotel/Tour/Taxi) keep their own
     * currency columns; this registry owns DISPLAY metadata + default.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique()->comment('ISO 4217 uppercase, e.g. INR, USD');
            $table->string('name', 100);
            $table->string('symbol', 12);
            $table->unsignedTinyInteger('decimal_digits')->default(2);
            $table->string('symbol_position', 6)->default('before')->comment('before|after');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default_display')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
