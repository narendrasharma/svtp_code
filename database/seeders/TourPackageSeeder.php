<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TourPackageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogDemoSeeder::class);
    }
}
