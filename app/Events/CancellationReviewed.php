<?php

namespace App\Events;

use App\Models\BookingCancellationRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an admin approves or rejects a cancellation request.
 */
class CancellationReviewed
{
    use Dispatchable;

    public function __construct(
        public BookingCancellationRequest $cancellation,
        public bool $approved,
    ) {}
}
