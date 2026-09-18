<?php

namespace App\Enums;

enum LeadPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Hot = 'hot';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Hot => 'Hot',
        };
    }
}
