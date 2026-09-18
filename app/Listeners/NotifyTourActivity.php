<?php

namespace App\Listeners;

use App\Events\TourModerated;
use App\Events\TourSubmitted;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Notifications\AdminAlert;
use App\Notifications\VendorAccountActivity;
use Illuminate\Events\Dispatcher;

/**
 * Tour moderation fan-out: admin alert on submission, vendor notice on
 * outcomes. No self-notice to the vendor on their own submission.
 */
class NotifyTourActivity
{
    use NotifiesAdmins;

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(TourSubmitted::class, [self::class, 'onSubmitted']);
        $events->listen(TourModerated::class, [self::class, 'onModerated']);
    }

    public function onSubmitted(TourSubmitted $event): void
    {
        $this->notifyAdmins(new AdminAlert('tour_submitted', [
            'tour' => $event->tour->title,
            'tour_id' => $event->tour->id,
        ]));
    }

    public function onModerated(TourModerated $event): void
    {
        $owner = $event->tour->vendorProfile?->user;

        if (! $owner) {
            return;
        }

        $kind = match ($event->decision) {
            'approved' => 'tour_approved',
            'changes_requested' => 'tour_changes_requested',
            default => 'tour_rejected',
        };

        $owner->notify(new VendorAccountActivity($kind, [
            'tour' => $event->tour->title,
            'reason' => $event->tour->review_note ?? $event->tour->rejection_reason ?? null,
        ]));
    }
}
