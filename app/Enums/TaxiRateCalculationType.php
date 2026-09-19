<?php

namespace App\Enums;

enum TaxiRateCalculationType: string
{
    case Fixed = 'fixed';
    case PerKilometer = 'per_km';
    case PerHour = 'per_hour';
    case PerDay = 'per_day';
    case Percentage = 'percentage';
    case Actual = 'actual';
    case Included = 'included';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::PerKilometer => 'Per kilometer',
            self::PerHour => 'Per hour',
            self::PerDay => 'Per day',
            self::Percentage => 'Percentage',
            self::Actual => 'Authorized actual amount',
            self::Included => 'Included',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
