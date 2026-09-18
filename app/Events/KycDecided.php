<?php

namespace App\Events;

use App\Models\VendorVerification;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when KYC reaches a terminal-ish outcome (verified|rejected|
 * needs_resubmission). Not fired for intermediate uploads.
 */
class KycDecided
{
    use Dispatchable;

    public function __construct(
        public VendorVerification $verification,
        public string $decision,
    ) {}
}
