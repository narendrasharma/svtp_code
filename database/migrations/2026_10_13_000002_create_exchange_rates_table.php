<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 13B: exchange-rate storage, normalized to ONE canonical
     * platform FX base (default USD, see currency.fx_base). Cross
     * conversions derive mathematically — no pair permutations stored.
     * Rates are DECIMAL, never float.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('base_currency_code', 3);
            $table->string('quote_currency_code', 3);
            $table->decimal('rate', 20, 10);
            $table->string('source', 20)->default('manual')->comment('manual|provider key');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['base_currency_code', 'quote_currency_code'], 'exchange_rates_pair_unique');
            $table->index(['quote_currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
