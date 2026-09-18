<?php

namespace App\Enums;

enum VendorApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ResubmissionRequested = 'resubmission_requested';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::ResubmissionRequested => 'Resubmission Requested',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::ResubmissionRequested => 'info',
        };
    }
}
