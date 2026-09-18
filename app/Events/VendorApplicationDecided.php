<?php

namespace App\Events;

use App\Models\VendorApplication;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an admin approves, rejects or requests resubmission.
 * Decision is a plain string (approved|rejected|resubmission) to keep the
 * event decoupled from the application status enum.
 */
class VendorApplicationDecided
{
    use Dispatchable;

    public function __construct(
        public VendorApplication $application,
        public string $decision,
    ) {}
}
