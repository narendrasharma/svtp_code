<?php

namespace App\Console\Commands\Operations;

use App\Services\OperationalReminders;
use Illuminate\Console\Command;

class RemindFollowUpsCommand extends Command
{
    protected $signature = 'ops:remind-followups';

    protected $description = 'Queue due and overdue CRM follow-up reminders (idempotent, no spam).';

    public function handle(OperationalReminders $operations): int
    {
        $result = $operations->remindFollowUps();
        $this->info("Follow-up reminders queued: {$result['due']} due, {$result['overdue']} overdue.");

        return self::SUCCESS;
    }
}
