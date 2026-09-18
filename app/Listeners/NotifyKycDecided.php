<?php

namespace App\Listeners;

use App\Events\KycDecided;
use App\Notifications\VendorAccountActivity;

/**
 * KYC outcome notice to the vendor user.
 */
class NotifyKycDecided
{
    public function handle(KycDecided $event): void
    {
        $user = $event->verification->user;

        if (! $user) {
            return;
        }

        $kind = match ($event->decision) {
            'verified' => 'kyc_verified',
            'rejected' => 'kyc_rejected',
            default => 'kyc_resubmission',
        };

        $user->notify(new VendorAccountActivity($kind, [
            'reason' => $event->verification->rejection_reason,
        ]));
    }
}
