<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Queue + scheduler + environment health snapshot (11.5D).
 *
 * Never exposes secrets. Statuses are computed from real signals
 * (heartbeats, job counts, writability) — never from the mere
 * existence of cron documentation.
 */
class SystemHealthService
{
    public const SCHEDULER_HEALTHY_SECONDS = 300;

    public const QUEUE_HEALTHY_SECONDS = 600;

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $queueConnection = (string) config('queue.default', 'database');
        $schedulerRun = $this->schedulerLastRunAt();
        $queueRun = $this->queueLastRunAt();

        $schedulerStatus = $this->schedulerStatus($schedulerRun);
        $queueStatus = $this->queueStatus($queueConnection, $queueRun);

        return [
            'app' => [
                'name' => (string) config('app.name'),
                'version' => (string) (config('app.version') ?? $this->detectAppVersion()),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'env' => (string) config('app.env'),
                'debug' => (bool) config('app.debug'),
                'timezone' => (string) config('app.timezone'),
                'url' => (string) config('app.url'),
            ],
            'drivers' => [
                'database' => (string) config('database.default'),
                'queue' => $queueConnection,
                'cache' => (string) config('cache.default'),
                'mail' => (string) config('mail.default', config('mail.mailer')),
                'broadcast' => (string) (config('broadcasting.default') ?? 'null'),
            ],
            'scheduler' => [
                'last_run_at' => $schedulerRun?->toDateTimeString(),
                'status' => $schedulerStatus['status'],
                'detail' => $schedulerStatus['detail'],
                'cron' => '* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1',
            ],
            'queue' => [
                'status' => $queueStatus['status'],
                'detail' => $queueStatus['detail'],
                'last_heartbeat_at' => $queueRun?->toDateTimeString(),
                'pending' => $this->pendingJobs(),
                'failed' => $this->failedJobs(),
                'last_failure_at' => $this->lastFailureAt(),
                'worker_command' => 'php artisan queue:work --tries=3 --backoff=60',
            ],
            'storage' => [
                'storage_writable' => is_writable(storage_path()),
                'cache_writable' => is_writable(base_path('bootstrap/cache')),
                'public_link' => is_link(public_path('storage')) || is_dir(public_path('storage')),
            ],
            'mail_note' => $this->mailNote(),
        ];
    }

    public function recordSchedulerHeartbeat(): void
    {
        Setting::setValue('system.scheduler_last_run_at', now()->toDateTimeString());
    }

    public function recordQueueHeartbeat(): void
    {
        Setting::setValue('system.queue_last_run_at', now()->toDateTimeString());
    }

    public function schedulerLastRunAt(): ?Carbon
    {
        $raw = Setting::getValue('system.scheduler_last_run_at');

        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function queueLastRunAt(): ?Carbon
    {
        $raw = Setting::getValue('system.queue_last_run_at');

        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{status: string, detail: string} */
    public function schedulerStatus(?Carbon $lastRun): array
    {
        if (! $lastRun) {
            return ['status' => 'not_detected', 'detail' => 'No scheduler heartbeat recorded yet. Configure the every-minute schedule:run cron.'];
        }

        if ($lastRun->diffInSeconds(now()) <= self::SCHEDULER_HEALTHY_SECONDS) {
            return ['status' => 'healthy', 'detail' => 'Scheduler heartbeat is fresh ('.$lastRun->diffForHumans().').'];
        }

        return ['status' => 'warning', 'detail' => 'Scheduler heartbeat is stale — last run '.$lastRun->diffForHumans().'. Check the server cron.'];
    }

    /** @return array{status: string, detail: string} */
    public function queueStatus(string $connection, ?Carbon $lastHeartbeat): array
    {
        if ($connection === 'sync') {
            return ['status' => 'sync', 'detail' => 'Queue driver is synchronous: jobs run inline, no worker needed.'];
        }

        if ($connection === 'null') {
            return ['status' => 'not_configured', 'detail' => 'Queue driver discards jobs. Set QUEUE_CONNECTION=database for background work.'];
        }

        if (! $lastHeartbeat) {
            return ['status' => 'not_detected', 'detail' => 'No queue heartbeat yet. Start a worker: php artisan queue:work.'];
        }

        if ($lastHeartbeat->diffInSeconds(now()) <= self::QUEUE_HEALTHY_SECONDS) {
            return ['status' => 'healthy', 'detail' => 'Queue worker heartbeat is fresh ('.$lastHeartbeat->diffForHumans().').'];
        }

        return ['status' => 'warning', 'detail' => 'Queue worker heartbeat is stale — last seen '.$lastHeartbeat->diffForHumans().'.'];
    }

    public function pendingJobs(): ?int
    {
        try {
            if (config('queue.default') !== 'database') {
                return null;
            }

            if (! Schema::hasTable('jobs')) {
                return null;
            }

            return (int) DB::table('jobs')->count();
        } catch (\Throwable) {
            return null;
        }
    }

    public function failedJobs(): int
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return 0;
            }

            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function lastFailureAt(): ?string
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return null;
            }

            $row = DB::table('failed_jobs')->orderByDesc('failed_at')->first();

            return $row?->failed_at;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function mailNote(): ?string
    {
        $mailer = (string) (config('mail.default') ?? config('mail.mailer') ?? '');

        if (in_array($mailer, ['log', 'array', 'null'], true)) {
            return 'Mail is in development/log mode — emails are not delivered to recipients.';
        }

        return null;
    }

    protected function detectAppVersion(): string
    {
        try {
            $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

            return (string) ($composer['version'] ?? 'dev');
        } catch (\Throwable) {
            return 'dev';
        }
    }

    /**
     * Safe failed-job listing: metadata only, never raw payloads.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function failedJobSummary(int $perPage = 15, int $page = 1): array
    {
        try {
            $query = DB::table('failed_jobs')->orderByDesc('failed_at');
            $total = (int) (clone $query)->count();
            $rows = $query->forPage(max(1, $page), $perPage)->get();
        } catch (\Throwable) {
            return ['data' => [], 'total' => 0];
        }

        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'id' => $row->id,
                'uuid' => $row->uuid,
                'connection' => $row->connection,
                'queue' => $row->queue,
                'failed_at' => $row->failed_at,
                'exception_summary' => $this->exceptionSummary((string) $row->exception),
                'job_name' => $this->jobName((string) $row->payload),
            ];
        }

        return ['data' => $data, 'total' => $total];
    }

    protected function exceptionSummary(string $exception): string
    {
        $firstLine = strtok($exception, "\n") ?: '';

        return mb_substr(trim($firstLine), 0, 300);
    }

    protected function jobName(string $payload): string
    {
        try {
            $decoded = json_decode($payload, true);
            $name = $decoded['displayName'] ?? $decoded['job'] ?? null;

            if (is_string($name)) {
                // Strip serialization noise; keep class short-name.
                $parts = explode('\\', $name);

                return mb_substr(end($parts), 0, 120);
            }
        } catch (\Throwable) {
        }

        return 'job';
    }
}
