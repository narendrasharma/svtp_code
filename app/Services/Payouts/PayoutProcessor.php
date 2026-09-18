<?php

namespace App\Services\Payouts;

use App\Models\User;
use App\Models\VendorWithdrawalRequest;

/**
 * Payout provider seam (Phase 8).
 *
 * Manual settlement is the only implementation today; future providers
 * (RazorpayX, Cashfree, Stripe Connect) implement this interface without
 * touching controllers or ledger accounting. Providers never write ledger
 * rows directly — settlement stays inside VendorLedgerService so
 * hold → release + settlement idempotency is preserved by construction.
 *
 * Future domain events (not built yet): WithdrawalApproved,
 * WithdrawalRejected, WithdrawalPaid, PayoutAccountSubmitted,
 * PayoutAccountVerified, RefundProcessed.
 */
interface PayoutProcessor
{
    public function key(): string;

    public function label(): string;

    /**
     * Settle an approved withdrawal. Must be idempotent: settling an
     * already-paid request returns it unchanged with no new entries.
     */
    public function settle(
        VendorWithdrawalRequest $withdrawal,
        User $admin,
        ?string $payoutReference = null,
        ?string $adminNote = null,
    ): VendorWithdrawalRequest;
}
