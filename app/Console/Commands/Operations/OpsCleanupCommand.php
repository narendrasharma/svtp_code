<?php

namespace App\Console\Commands\Operations;

use App\Models\AccountInvitation;
use App\Services\TaxiDriverLocationService;
use App\Support\OperationsSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Conservative maintenance. Only removes:
 * - read notifications older than the configured retention window;
 * - nothing else.
 *
 * Financial records, audit logs, booking history, support history and
 * communication records are NEVER deleted here.
 */
class OpsCleanupCommand extends Command
{
    protected $signature = 'ops:cleanup';

    protected $description = 'Run conservative system cleanup (expired invitations, old read notifications).';

    public function handle(): int
    {
        $expiredInvitations = AccountInvitation::whereNull('used_at')
            ->where('expires_at', '<', now())
            ->count();

        $retentionDays = max(30, (int) (OperationsSettings::get('ops.notification_retention_days') ?? 180));
        $cutoff = now()->subDays($retentionDays)->toDateTimeString();

        $oldNotifications = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff)
            ->delete();

        // Phase 12A.5: purge raw driver location telemetry only. Booking
        // and assignment records are operational history and are never
        // touched here.
        $oldPings = app(TaxiDriverLocationService::class)->purgeExpired();

        $this->info("Cleanup: {$expiredInvitations} expired invitations noted, {$oldNotifications} old read notifications pruned, {$oldPings} old driver location pings pruned.");

        return self::SUCCESS;
    }
}
