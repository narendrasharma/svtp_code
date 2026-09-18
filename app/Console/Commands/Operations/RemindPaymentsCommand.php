<?php

namespace App\Console\Commands\Operations;

use App\Services\OperationalReminders;
use Illuminate\Console\Command;

class RemindPaymentsCommand extends Command
{
    protected $signature = 'ops:remind-payments';

    protected $description = 'Queue payment-due reminders for bookings with a configured due date and outstanding balance.';

    public function handle(OperationalReminders $operations): int
    {
        $count = $operations->remindPayments();
        $this->info("Payment reminders queued: {$count}.");

        return self::SUCCESS;
    }
}
