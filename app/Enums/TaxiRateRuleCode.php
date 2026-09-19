<?php

namespace App\Enums;

enum TaxiRateRuleCode: string
{
    case BaseFare = 'base_fare';
    case MinimumFare = 'minimum_fare';
    case DistanceRate = 'distance_rate';
    case ExtraDistanceRate = 'extra_distance_rate';
    case DriverAllowance = 'driver_allowance';
    case NightCharge = 'night_charge';
    case WaitingCharge = 'waiting_charge';
    case Toll = 'toll';
    case Parking = 'parking';
    case Tax = 'tax';

    public function label(): string
    {
        return match ($this) {
            self::BaseFare => 'Base fare',
            self::MinimumFare => 'Minimum fare',
            self::DistanceRate => 'Distance rate',
            self::ExtraDistanceRate => 'Extra distance rate',
            self::DriverAllowance => 'Driver allowance',
            self::NightCharge => 'Night charge',
            self::WaitingCharge => 'Waiting charge',
            self::Toll => 'Toll',
            self::Parking => 'Parking',
            self::Tax => 'Tax',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
