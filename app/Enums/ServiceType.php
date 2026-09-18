<?php

namespace App\Enums;

/**
 * Shared service verticals. Tours is functional today; taxi/hotel leads
 * and quotations are storable before those modules ship, but converting
 * them into product bookings requires the module to be available.
 */
enum ServiceType: string
{
    case Tour = 'tour';
    case Taxi = 'taxi';
    case Hotel = 'hotel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tour => 'Tour',
            self::Taxi => 'Taxi',
            self::Hotel => 'Hotel',
            self::Other => 'Other',
        };
    }
}
