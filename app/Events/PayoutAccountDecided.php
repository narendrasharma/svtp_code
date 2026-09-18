<?php

namespace App\Events;

use App\Models\VendorPayoutAccount;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an admin verifies or rejects a payout account.
 */
class PayoutAccountDecided
{
    use Dispatchable;

    public function __construct(
        public VendorPayoutAccount $account,
        public string $decision,
    ) {}
}
