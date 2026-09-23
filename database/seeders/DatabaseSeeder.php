<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@shreevrindavantourpackages.com'],
            ['name' => 'Kanhaiya Upadhyay', 'password' => bcrypt('change-this-password'), 'role' => 'admin']
        );

        $this->call([
            LanguageSeeder::class,
            CurrencySeeder::class,
            HomepageSectionSeeder::class,
            CatalogDemoSeeder::class,
        ]);
    }
}
