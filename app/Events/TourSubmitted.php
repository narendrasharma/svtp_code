<?php

namespace App\Events;

use App\Models\TourPackage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a vendor submits a tour for moderation. Admin audience notified.
 */
class TourSubmitted
{
    use Dispatchable;

    public function __construct(public TourPackage $tour) {}
}
