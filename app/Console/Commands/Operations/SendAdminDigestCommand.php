<?php

namespace App\Console\Commands\Operations;

use App\Services\AdminDigestService;
use Illuminate\Console\Command;

class SendAdminDigestCommand extends Command
{
    protected $signature = 'ops:digest {--force= : Force a digest run (daily|weekly) regardless of settings}';

    protected $description = 'Send the optional admin operations digest (off by default; daily|weekly).';

    public function handle(AdminDigestService $digests): int
    {
        $forced = $this->option('force');

        if ($forced === 'daily') {
            $sent = $digests->sendDaily();
        } elseif ($forced === 'weekly') {
            $sent = $digests->sendWeekly();
        } else {
            $sent = $digests->maybeSendDaily() + $digests->maybeSendWeekly();
        }

        $this->info("Admin digests sent: {$sent}.");

        return self::SUCCESS;
    }
}
