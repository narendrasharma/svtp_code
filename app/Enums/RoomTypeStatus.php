<?php

namespace App\Enums;

/**
 * Room type visibility lifecycle (12B.2). Deliberately simpler than the
 * property publishing workflow — no review queue. Public only when the
 * parent property is published AND the room type is active.
 */
enum RoomTypeStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
