<?php

namespace Database\Seeders;

use App\Services\HomepageSectionService;
use Illuminate\Database\Seeder;

class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        app(HomepageSectionService::class)->ensureMerchandisingStored();
    }
}
