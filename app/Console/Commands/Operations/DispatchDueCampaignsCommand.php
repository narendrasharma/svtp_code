<?php

namespace App\Console\Commands\Operations;

use App\Services\OperationalReminders;
use Illuminate\Console\Command;

class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'ops:dispatch-campaigns';

    protected $description = 'Dispatch scheduled campaigns whose scheduled_at has passed (cancelled campaigns never send).';

    public function handle(OperationalReminders $operations): int
    {
        $count = $operations->dispatchDueCampaigns();
        $this->info("Scheduled campaigns dispatched: {$count}.");

        return self::SUCCESS;
    }
}
