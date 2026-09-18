<?php

namespace App\Console\Commands\Operations;

use App\Services\OperationalReminders;
use Illuminate\Console\Command;

class ExpireQuotationsCommand extends Command
{
    protected $signature = 'ops:expire-quotations';

    protected $description = 'Mark past-validity open quotations expired and queue expiring-soon reminders.';

    public function handle(OperationalReminders $operations): int
    {
        $result = $operations->expireQuotations();
        $this->info("Quotations: {$result['expired']} expired, {$result['reminded']} reminders queued.");

        return self::SUCCESS;
    }
}
