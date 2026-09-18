<?php

namespace App\Enums;

/**
 * Payout account verification (Phase 8).
 *
 * Deliberately separate from KYC: identity/business verification
 * (VendorVerification) and payout-destination verification are independent.
 * A replacement submission resets to pending.
 */
enum PayoutAccountStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }
}
