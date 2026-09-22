<?php

namespace App\Console\Commands\Operations;

use App\Services\TaxiAutoDispatchService;
use App\Support\ModuleManager;
use Illuminate\Console\Command;

/**
 * Expire due auto-dispatch offers and advance each affected booking by
 * one candidate. Idempotent and chunked — safe to run frequently.
 */
class ExpireDispatchOffersCommand extends Command
{
    protected $signature = 'taxi:expire-dispatch-offers';

    protected $description = 'Expire due taxi dispatch offers and advance bookings to the next candidate.';

    public function handle(TaxiAutoDispatchService $autoDispatch): int
    {
        if (app(ModuleManager::class)->isDisabled('taxi')) {
            $this->info('Taxi module is disabled; nothing to expire.');

            return self::SUCCESS;
        }

        $result = $autoDispatch->expireDueOffers();

        $this->info("Dispatch offers: {$result['expired']} expired, {$result['advanced']} bookings advanced.");

        return self::SUCCESS;
    }
}
