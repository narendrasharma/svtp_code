<?php

namespace App\Console\Commands\Operations;

use App\Models\AccountInvitation;
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

        $this->info("Cleanup: {$expiredInvitations} expired invitations noted, {$oldNotifications} old read notifications pruned.");

        return self::SUCCESS;
    }
}
