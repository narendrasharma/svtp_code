<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Curated country starter set (12B.4.1).
 *
 * Idempotent (firstOrCreate by ISO2): safe to re-run, never duplicates
 * or touches admin edits. Deliberately a starter set, not a world list —
 * buyers add countries from Admin → Locations → Countries.
 *
 * Invoked from CatalogDemoSeeder; also runnable standalone:
 *
 *   php artisan db:seed --class=CountrySeeder
 */
class CountrySeeder extends Seeder
{
    /**
     * @return array<int, array{name:string,iso2:string,iso3:string,phone_code:string,currency_code:string,sort_order:int}>
     */
    public static function dataset(): array
    {
        return [
            ['name' => 'India', 'iso2' => 'IN', 'iso3' => 'IND', 'phone_code' => '+91', 'currency_code' => 'INR', 'sort_order' => 1],
            ['name' => 'United States', 'iso2' => 'US', 'iso3' => 'USA', 'phone_code' => '+1', 'currency_code' => 'USD', 'sort_order' => 2],
            ['name' => 'United Kingdom', 'iso2' => 'GB', 'iso3' => 'GBR', 'phone_code' => '+44', 'currency_code' => 'GBP', 'sort_order' => 3],
            ['name' => 'United Arab Emirates', 'iso2' => 'AE', 'iso3' => 'ARE', 'phone_code' => '+971', 'currency_code' => 'AED', 'sort_order' => 4],
            ['name' => 'Thailand', 'iso2' => 'TH', 'iso3' => 'THA', 'phone_code' => '+66', 'currency_code' => 'THB', 'sort_order' => 5],
            ['name' => 'Singapore', 'iso2' => 'SG', 'iso3' => 'SGP', 'phone_code' => '+65', 'currency_code' => 'SGD', 'sort_order' => 6],
            ['name' => 'France', 'iso2' => 'FR', 'iso3' => 'FRA', 'phone_code' => '+33', 'currency_code' => 'EUR', 'sort_order' => 7],
            ['name' => 'Germany', 'iso2' => 'DE', 'iso3' => 'DEU', 'phone_code' => '+49', 'currency_code' => 'EUR', 'sort_order' => 8],
            ['name' => 'Italy', 'iso2' => 'IT', 'iso3' => 'ITA', 'phone_code' => '+39', 'currency_code' => 'EUR', 'sort_order' => 9],
            ['name' => 'Spain', 'iso2' => 'ES', 'iso3' => 'ESP', 'phone_code' => '+34', 'currency_code' => 'EUR', 'sort_order' => 10],
            ['name' => 'Australia', 'iso2' => 'AU', 'iso3' => 'AUS', 'phone_code' => '+61', 'currency_code' => 'AUD', 'sort_order' => 11],
            ['name' => 'Canada', 'iso2' => 'CA', 'iso3' => 'CAN', 'phone_code' => '+1', 'currency_code' => 'CAD', 'sort_order' => 12],
            ['name' => 'Japan', 'iso2' => 'JP', 'iso3' => 'JPN', 'phone_code' => '+81', 'currency_code' => 'JPY', 'sort_order' => 13],
            ['name' => 'Nepal', 'iso2' => 'NP', 'iso3' => 'NPL', 'phone_code' => '+977', 'currency_code' => 'NPR', 'sort_order' => 14],
        ];
    }

    public function run(): void
    {
        foreach (static::dataset() as $row) {
            Country::firstOrCreate(['iso2' => $row['iso2']], $row + ['is_active' => true]);
        }
    }
}
