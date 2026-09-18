<?php

namespace App\Listeners\Concerns;

use App\Models\User;
use App\Notifications\AdminAlert;

/**
 * Shared admin audience fan-out for operational alerts.
 */
trait NotifiesAdmins
{
    protected function notifyAdmins(AdminAlert $alert): void
    {
        foreach (User::where('role', 'admin')->cursor() as $admin) {
            $admin->notify($alert);
        }
    }
}
