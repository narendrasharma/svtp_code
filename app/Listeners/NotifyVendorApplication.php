<?php

namespace App\Listeners;

use App\Events\VendorApplicationDecided;
use App\Events\VendorApplicationSubmitted;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Notifications\AdminAlert;
use App\Notifications\VendorAccountActivity;
use Illuminate\Events\Dispatcher;

/**
 * Vendor application fan-out: applicant lifecycle + one admin alert on
 * submission (operational). Decisions reach only the applicant.
 */
class NotifyVendorApplication
{
    use NotifiesAdmins;

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(VendorApplicationSubmitted::class, [self::class, 'onSubmitted']);
        $events->listen(VendorApplicationDecided::class, [self::class, 'onDecided']);
    }

    public function onSubmitted(VendorApplicationSubmitted $event): void
    {
        $application = $event->application;

        if ($application->user) {
            $application->user->notify(new VendorAccountActivity('application_submitted'));
        }

        $this->notifyAdmins(new AdminAlert('application_submitted', [
            'applicant' => $application->user?->name ?? $application->business_name,
            'application_id' => $application->id,
        ]));
    }

    public function onDecided(VendorApplicationDecided $event): void
    {
        $application = $event->application;

        if (! $application->user) {
            return;
        }

        $kind = match ($event->decision) {
            'approved' => 'application_approved',
            'rejected' => 'application_rejected',
            default => 'application_resubmission',
        };

        $application->user->notify(new VendorAccountActivity($kind, [
            'reason' => $application->rejection_reason,
        ]));
    }
}
