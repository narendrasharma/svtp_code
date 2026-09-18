<?php

namespace App\Services\Payouts;

use App\Models\User;
use App\Models\VendorWithdrawalRequest;
use App\Services\VendorLedgerService;

/**
 * Manual (off-platform) payout settlement.
 *
 * Admin pays through their own banking channel, then records the payout
 * reference here. The reference is stored on the withdrawal and inside the
 * settlement entry metadata; ledger accounting is delegated untouched.
 */
class ManualPayoutProcessor implements PayoutProcessor
{
    public function __construct(protected VendorLedgerService $ledger) {}

    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Manual payout';
    }

    public function settle(
        VendorWithdrawalRequest $withdrawal,
        User $admin,
        ?string $payoutReference = null,
        ?string $adminNote = null,
    ): VendorWithdrawalRequest {
        return $this->ledger->markWithdrawalPaid($withdrawal, $admin, $payoutReference, $adminNote);
    }
}
