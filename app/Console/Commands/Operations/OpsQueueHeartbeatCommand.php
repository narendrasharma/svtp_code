<?php

namespace App\Console\Commands\Operations;

use App\Services\OperationalReminders;
use Illuminate\Console\Command;

class OpsQueueHeartbeatCommand extends Command
{
    protected $signature = 'ops:queue-heartbeat';

    protected $description = 'Dispatch a queue heartbeat job (proves a queue worker is processing).';

    public function handle(OperationalReminders $operations): int
    {
        $operations->dispatchQueueHeartbeat();
        $this->info('Queue heartbeat job dispatched.');

        return self::SUCCESS;
    }
}
