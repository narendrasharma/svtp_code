<?php

namespace App\Events;

use App\Models\VendorApplication;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a customer submits a vendor application. The applicant and the
 * admin audience (operational) are notified.
 */
class VendorApplicationSubmitted
{
    use Dispatchable;

    public function __construct(public VendorApplication $application) {}
}
