<?php

namespace App\Console\Commands\Operations;

use App\Services\SystemHealthService;
use Illuminate\Console\Command;

class OpsHeartbeatCommand extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Record the scheduler heartbeat (proves the every-minute cron is alive).';

    public function handle(SystemHealthService $health): int
    {
        $health->recordSchedulerHeartbeat();
        $this->info('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}
