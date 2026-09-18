<?php

namespace App\Events;

use App\Models\BookingRefund;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a manual accounting refund is processed. Listeners notify the
 * customer and the affected vendor (earning impact).
 */
class RefundRecorded
{
    use Dispatchable;

    public function __construct(public BookingRefund $refund) {}
}
