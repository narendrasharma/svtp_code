<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Support\CurrencyRegistry;
use Illuminate\Database\Seeder;

/**
 * Generic product defaults (Phase 13B). Idempotent.
 *
 * USD is the default DISPLAY currency (and the canonical FX base,
 * matching the Hotel pricing default `hotel.default_currency`).
 * INR/EUR/GBP ship active; AED ships as an inactive demo option.
 *
 * NO exchange rates are seeded and NO existing Hotel/Tour/Taxi
 * prices are touched: missing rates safely show authoritative
 * amounts until the admin enters manual rates.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_digits' => 2, 'symbol_position' => 'before', 'is_active' => true, 'is_default_display' => true, 'sort_order' => 0],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_digits' => 2, 'symbol_position' => 'before', 'is_active' => true, 'is_default_display' => false, 'sort_order' => 10],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_digits' => 2, 'symbol_position' => 'before', 'is_active' => true, 'is_default_display' => false, 'sort_order' => 20],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_digits' => 2, 'symbol_position' => 'before', 'is_active' => true, 'is_default_display' => false, 'sort_order' => 30],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'decimal_digits' => 2, 'symbol_position' => 'before', 'is_active' => false, 'is_default_display' => false, 'sort_order' => 40],
        ];

        foreach ($defaults as $row) {
            Currency::updateOrCreate(['code' => $row['code']], $row);
        }

        // Enforce exactly one default display (USD wins if drifted).
        Currency::query()->where('code', '!=', 'USD')->where('is_default_display', true)->update(['is_default_display' => false]);
        Currency::query()->where('code', 'USD')->update(['is_default_display' => true, 'is_active' => true]);

        CurrencyRegistry::forgetCache();
    }
}
