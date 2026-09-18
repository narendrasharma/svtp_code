<?php

namespace App\Enums;

/**
 * Taxi trip verticals. Only a subset is bookable in 12A.1 — the rest
 * are architectural placeholders so statuses, pricing and UI never
 * need renaming later.
 */
enum TripType: string
{
    case OneWay = 'one_way';
    case RoundTrip = 'round_trip';
    case Hourly = 'hourly';
    case Outstation = 'outstation';
    case AirportTransfer = 'airport_transfer';

    public function label(): string
    {
        return match ($this) {
            self::OneWay => 'One Way',
            self::RoundTrip => 'Round Trip',
            self::Hourly => 'Local / Hourly Rental',
            self::Outstation => 'Outstation',
            self::AirportTransfer => 'Airport Transfer',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function bookable(): array
    {
        return [self::OneWay->value, self::AirportTransfer->value];
    }
}
