<?php

namespace App\Console\Commands\Operations;

use App\Services\OperationalReminders;
use Illuminate\Console\Command;

class RemindTravelCommand extends Command
{
    protected $signature = 'ops:remind-travel';

    protected $description = 'Queue customer and vendor travel reminders at configured day offsets.';

    public function handle(OperationalReminders $operations): int
    {
        $result = $operations->remindTravel();
        $this->info("Travel reminders queued: {$result['customer']} customer, {$result['vendor']} vendor.");

        return self::SUCCESS;
    }
}
