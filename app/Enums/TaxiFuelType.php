<?php

namespace App\Enums;

enum TaxiFuelType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Cng = 'cng';
    case Electric = 'electric';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return ucfirst($this->value === 'cng' ? 'CNG' : $this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
