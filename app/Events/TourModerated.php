<?php

namespace App\Events;

use App\Models\TourPackage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on moderation outcomes (approved|changes_requested|rejected).
 */
class TourModerated
{
    use Dispatchable;

    public function __construct(
        public TourPackage $tour,
        public string $decision,
    ) {}
}
