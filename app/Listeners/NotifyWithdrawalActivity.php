<?php

namespace App\Listeners;

use App\Enums\WithdrawalStatus;
use App\Events\WithdrawalDecided;
use App\Events\WithdrawalRequested;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Notifications\AdminAlert;
use App\Notifications\VendorAccountActivity;
use Illuminate\Events\Dispatcher;

/**
 * Withdrawal fan-out: vendor confirmation + status updates, plus one admin
 * alert per request. Events fire on domain transitions (which are already
 * auth-guarded); impersonation cannot trigger financial transitions, so no
 * phantom finance notifications can arise from support impersonation.
 */
class NotifyWithdrawalActivity
{
    use NotifiesAdmins;

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(WithdrawalRequested::class, [self::class, 'onRequested']);
        $events->listen(WithdrawalDecided::class, [self::class, 'onDecided']);
    }

    public function onRequested(WithdrawalRequested $event): void
    {
        $withdrawal = $event->withdrawal->loadMissing('vendorProfile.user');
        $vendorUser = $withdrawal->vendorProfile?->user;

        if ($vendorUser) {
            $vendorUser->notify(new VendorAccountActivity('withdrawal_requested', [
                'amount' => (string) $withdrawal->amount,
            ]));
        }

        $this->notifyAdmins(new AdminAlert('withdrawal_requested', [
            'amount' => (string) $withdrawal->amount,
            'vendor' => $withdrawal->vendorProfile?->business_name ?? 'a vendor',
            'withdrawal_id' => $withdrawal->id,
        ]));
    }

    public function onDecided(WithdrawalDecided $event): void
    {
        $withdrawal = $event->withdrawal->loadMissing('vendorProfile.user');
        $vendorUser = $withdrawal->vendorProfile?->user;

        if (! $vendorUser) {
            return;
        }

        $kind = match ($event->status) {
            WithdrawalStatus::Approved => 'withdrawal_approved',
            WithdrawalStatus::Rejected => 'withdrawal_rejected',
            WithdrawalStatus::Paid => 'withdrawal_paid',
            default => 'withdrawal_cancelled',
        };

        $vendorUser->notify(new VendorAccountActivity($kind, [
            'amount' => (string) $withdrawal->amount,
            'reason' => $withdrawal->rejection_reason,
            'reference' => $withdrawal->payout_reference,
        ]));
    }
}
