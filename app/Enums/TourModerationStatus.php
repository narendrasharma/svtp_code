<?php

namespace App\Enums;

enum TourModerationStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes Requested',
            self::Rejected => 'Rejected',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
