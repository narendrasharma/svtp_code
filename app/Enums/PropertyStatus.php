<?php

namespace App\Enums;

/**
 * Property publishing lifecycle (12B.1).
 *
 * Single-column workflow shared by platform and vendor properties.
 * `published` is the only publicly visible state.
 */
enum PropertyStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Inactive = 'inactive';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Published => 'Published',
            self::Inactive => 'Inactive',
            self::Rejected => 'Rejected',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
