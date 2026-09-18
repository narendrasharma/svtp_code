<?php

namespace App\Events;

use App\Models\VendorWithdrawalRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a vendor requests a withdrawal. Vendor (confirmation) and the
 * admin audience (operational) are notified.
 */
class WithdrawalRequested
{
    use Dispatchable;

    public function __construct(public VendorWithdrawalRequest $withdrawal) {}
}
