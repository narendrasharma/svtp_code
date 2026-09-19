<?php

namespace App\Enums;

/**
 * Driver earning calculation models (Phase 12A.10).
 *
 * One plan uses exactly ONE primary calculation type; hybrid adds a
 * percentage on top of a fixed base. Allowance pass-through and the
 * minimum floor are orthogonal flags/columns, not separate types.
 */
enum TaxiDriverEarningCalculation: string
{
    case Fixed = 'fixed';
    case PercentTotal = 'percent_total';
    case PercentBase = 'percent_base';
    case PerKm = 'per_km';
    case PerHour = 'per_hour';
    case Hybrid = 'hybrid';
    case NoShowFixed = 'no_show_fixed';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed per trip',
            self::PercentTotal => '% of booking total',
            self::PercentBase => '% of base fare',
            self::PerKm => 'Per kilometer',
            self::PerHour => 'Per hour',
            self::Hybrid => 'Fixed + % of total',
            self::NoShowFixed => 'No-show compensation',
            self::Manual => 'Manual',
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
