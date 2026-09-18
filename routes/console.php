<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Platform operations scheduler (Phase 11.5D).
 *
 * ONE server cron drives everything:
 * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
 *
 * Never add per-feature Linux crons. Frequencies are chosen to avoid
 * notification spam: heartbeats are cheap and frequent, reminders are
 * idempotent and run at most a few times per hour/day.
 */
Schedule::command('ops:heartbeat')->everyMinute()->name('ops-heartbeat');

Schedule::command('ops:queue-heartbeat')->everyFiveMinutes()->name('ops-queue-heartbeat');

Schedule::command('ops:dispatch-campaigns')->everyFiveMinutes()->name('ops-dispatch-campaigns');

Schedule::command('ops:remind-followups')->everyFifteenMinutes()->name('ops-remind-followups');

Schedule::command('ops:expire-quotations')->hourly()->name('ops-expire-quotations');

Schedule::command('ops:remind-payments')->dailyAt('09:00')->name('ops-remind-payments');

Schedule::command('ops:remind-travel')->dailyAt('08:00')->name('ops-remind-travel');

Schedule::command('ops:digest')->dailyAt('07:30')->name('ops-digest');

Schedule::command('ops:cleanup')->dailyAt('03:00')->name('ops-cleanup');
