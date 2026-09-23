<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Support\Localization;
use Illuminate\Database\Seeder;

/**
 * Generic product defaults (Phase 13A). Idempotent.
 *
 * English: active + default. Hindi + Arabic ship as inactive demo
 * options (Arabic validates the RTL path) so fresh installs stay
 * single-language until the operator enables more.
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'code' => 'en',
                'locale' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_active' => true,
                'is_default' => true,
                'is_rtl' => false,
                'sort_order' => 0,
            ],
            [
                'code' => 'hi',
                'locale' => 'hi',
                'name' => 'Hindi',
                'native_name' => 'हिन्दी',
                'is_active' => false,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 10,
            ],
            [
                'code' => 'ar',
                'locale' => 'ar',
                'name' => 'Arabic',
                'native_name' => 'العربية',
                'is_active' => false,
                'is_default' => false,
                'is_rtl' => true,
                'sort_order' => 20,
            ],
        ];

        foreach ($defaults as $row) {
            Language::updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }

        // Enforce exactly one default (English wins if drifted).
        Language::query()->where('code', '!=', 'en')->where('is_default', true)->update(['is_default' => false]);
        Language::query()->where('code', 'en')->update(['is_default' => true, 'is_active' => true]);

        Localization::forgetCache();
    }
}
