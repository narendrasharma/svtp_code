<?php

namespace App\Enums;

/**
 * Driver availability (12A.1). Ride states (en_route/arrived/on-board)
 * live on the taxi booking — never here. A busy driver is derived:
 * availability=available + an active trip assignment.
 */
enum DriverAvailabilityStatus: string
{
    case Offline = 'offline';
    case Available = 'available';
    case Break = 'break';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Offline => 'Offline',
            self::Available => 'Available',
            self::Break => 'On Break',
            self::OnLeave => 'On Leave',
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
