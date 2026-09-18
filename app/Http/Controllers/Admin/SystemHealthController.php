<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use App\Support\OperationsSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System information / health (11.5D).
 *
 * Secrets are never included — see SystemHealthService::snapshot().
 */
class SystemHealthController extends Controller
{
    public function index(SystemHealthService $health): Response
    {
        return Inertia::render('Admin/System/Index', [
            'health' => $health->snapshot(),
            'operations' => OperationsSettings::all(),
            'queue_worker_docs' => [
                'command' => 'php artisan queue:work --tries=3 --backoff=60',
                'cron' => '* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1',
                'note' => 'Use Supervisor or systemd to keep the queue worker alive in production. The database queue driver is the default; Redis works without code changes.',
            ],
        ]);
    }
}
