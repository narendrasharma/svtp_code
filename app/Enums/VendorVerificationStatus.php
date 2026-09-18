<?php

namespace App\Enums;

enum VendorVerificationStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case NeedsResubmission = 'needs_resubmission';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Pending => 'Pending',
            self::UnderReview => 'Under Review',
            self::Verified => 'Verified',
            self::NeedsResubmission => 'Needs Resubmission',
            self::Rejected => 'Rejected',
        };
    }
}
