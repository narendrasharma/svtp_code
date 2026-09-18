<?php

namespace App\Events;

use App\Enums\WithdrawalStatus;
use App\Models\VendorWithdrawalRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on review transitions (approved|rejected|paid|cancelled).
 */
class WithdrawalDecided
{
    use Dispatchable;

    public function __construct(
        public VendorWithdrawalRequest $withdrawal,
        public WithdrawalStatus $status,
    ) {}
}
