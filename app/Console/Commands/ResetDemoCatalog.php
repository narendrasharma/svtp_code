<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:reset-demo')]
#[Description('Safely restore the known tour catalog demo records without deleting business data')]
class ResetDemoCatalog extends Command
{
    public function handle(CatalogDemoSeeder $seeder): int
    {
        $this->components->info('Updating known demo tours in place. Users, bookings, enquiries, reviews, and non-demo catalog records are preserved.');

        $seeder->setContainer($this->laravel)->setCommand($this)->run();

        $this->components->info('Tour catalog demo data is ready (18 tours).');

        return self::SUCCESS;
    }
}
