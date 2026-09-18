<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Failed-job visibility (11.5D).
 *
 * Lists SAFE metadata only — never raw payloads. Retry/delete use the
 * framework's own queue commands and require system.jobs.manage.
 */
class FailedJobController extends Controller
{
    public function index(Request $request, SystemHealthService $health): Response
    {
        $page = max(1, (int) $request->integer('page', 1));
        $summary = $health->failedJobSummary(15, $page);

        return Inertia::render('Admin/System/FailedJobs', [
            'jobs' => $summary['data'],
            'total' => $summary['total'],
            'page' => $page,
            'perPage' => 15,
        ]);
    }

    public function retry(Request $request, string $uuid, SystemHealthService $health): RedirectResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
        ]);

        $exit = Artisan::call('queue:retry', ['id' => [$uuid]]);

        app(ActivityLogger::class)->log('system.job_retried', 'system', 'Failed job retried: '.$uuid);

        return back()->with('flash', $exit === 0 ? 'Job queued for retry.' : 'Retry requested.');
    }

    public function destroy(string $uuid): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $uuid]);

        app(ActivityLogger::class)->log('system.job_forgotten', 'system', 'Failed job record deleted: '.$uuid);

        return back()->with('flash', 'Failed job record deleted.');
    }
}
