<?php

namespace App\Jobs;

use App\Services\SystemHealthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queue worker heartbeat. Dispatched by the scheduler every few
 * minutes; when the worker is alive this job runs and refreshes the
 * queue heartbeat. Scheduler-alive + heartbeat-stale therefore means
 * "cron works, worker does not".
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public function handle(SystemHealthService $health): void
    {
        $health->recordQueueHeartbeat();
    }
}
