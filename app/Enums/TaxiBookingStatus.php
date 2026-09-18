<?php

namespace App\Enums;

/**
 * Taxi booking/ride lifecycle (12A.1).
 *
 * Ride state only — never payment state (PaymentStatus), driver
 * availability (DriverAvailabilityStatus) or vehicle ops state
 * (VehicleStatus). `confirmed` implies "awaiting driver assignment";
 * no separate driver_unassigned state.
 */
enum TaxiBookingStatus: string
{
    case Draft = 'draft';
    case Quoted = 'quoted';
    case Confirmed = 'confirmed';
    case DriverAssigned = 'driver_assigned';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case PassengerOnBoard = 'passenger_on_board';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Quoted, self::Confirmed, self::Cancelled],
            self::Quoted => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::DriverAssigned, self::Cancelled],
            self::DriverAssigned => [self::EnRoute, self::Confirmed, self::Cancelled, self::NoShow],
            self::EnRoute => [self::Arrived, self::Cancelled, self::NoShow],
            self::Arrived => [self::PassengerOnBoard, self::Cancelled, self::NoShow],
            self::PassengerOnBoard => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }

    public function isActiveTrip(): bool
    {
        return in_array($this, [self::DriverAssigned, self::EnRoute, self::Arrived, self::PassengerOnBoard], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Quoted => 'Quoted',
            self::Confirmed => 'Confirmed',
            self::DriverAssigned => 'Driver Assigned',
            self::EnRoute => 'En Route',
            self::Arrived => 'Arrived',
            self::PassengerOnBoard => 'On Board',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
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
