<?php

namespace App\Enums;

enum TaxiVehicleDocumentType: string
{
    case Rc = 'rc';
    case Insurance = 'insurance';
    case Permit = 'permit';
    case Pollution = 'pollution';
    case Fitness = 'fitness';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rc => 'Registration Certificate',
            self::Insurance => 'Insurance',
            self::Permit => 'Permit',
            self::Pollution => 'Pollution Certificate',
            self::Fitness => 'Fitness Certificate',
            self::Other => 'Other',
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
