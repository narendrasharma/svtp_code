<?php

namespace App\Listeners;

use App\Events\PayoutAccountDecided;
use App\Notifications\VendorAccountActivity;

/**
 * Payout destination outcome notice. Only the masked destination travels in
 * the payload — never secrets.
 */
class NotifyPayoutAccountDecided
{
    public function handle(PayoutAccountDecided $event): void
    {
        $vendorUser = $event->account->vendorProfile?->user;

        if (! $vendorUser) {
            return;
        }

        $vendorUser->notify(new VendorAccountActivity(
            $event->decision === 'verified' ? 'payout_verified' : 'payout_rejected',
            [
                'destination' => $event->account->maskedDestination(),
                'reason' => $event->account->rejection_reason,
            ]
        ));
    }
}
