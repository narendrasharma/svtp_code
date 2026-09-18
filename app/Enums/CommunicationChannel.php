<?php

namespace App\Enums;

/**
 * Communication channels. sms/whatsapp require a configured provider;
 * without one the option is disabled (never faked). manual_share records
 * a staff-generated wa.me / copy-link share.
 */
enum CommunicationChannel: string
{
    case Email = 'email';
    case InApp = 'in_app';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case ManualShare = 'manual_share';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::InApp => 'In-app notification',
            self::Sms => 'SMS',
            self::Whatsapp => 'WhatsApp',
            self::ManualShare => 'Manual share',
        };
    }
}
