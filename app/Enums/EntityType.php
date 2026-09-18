<?php

namespace App\Enums;

enum EntityType: string
{
    case Individual = 'individual';
    case SoleProprietor = 'sole_proprietor';
    case Partnership = 'partnership';
    case Llp = 'llp';
    case PrivateLimited = 'private_limited';
    case PublicLimited = 'public_limited';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::SoleProprietor => 'Sole Proprietor',
            self::Partnership => 'Partnership',
            self::Llp => 'LLP',
            self::PrivateLimited => 'Private Limited',
            self::PublicLimited => 'Public Limited',
            self::Other => 'Other',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
