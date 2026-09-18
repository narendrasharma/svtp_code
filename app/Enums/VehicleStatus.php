<?php

namespace App\Enums;

/**
 * Vehicle operational state (12A.1). Separate from the is_active flag
 * (commercially enabled) and from ride status on the booking.
 */
enum VehicleStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case OnTrip = 'on_trip';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Assigned => 'Assigned',
            self::OnTrip => 'On Trip',
            self::Maintenance => 'Maintenance',
            self::OutOfService => 'Out of Service',
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
