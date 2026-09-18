<?php

namespace App\Enums;

enum FollowUpType: string
{
    case Call = 'call';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Meeting = 'meeting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Meeting => 'Meeting',
            self::Other => 'Other',
        };
    }
}
