<?php

namespace App\Enums;

/**
 * Where a booking originated. Sources are created centrally here — never
 * raw strings scattered through controllers. Snapshotted on the booking.
 */
enum BookingSource: string
{
    case Website = 'website';
    case Admin = 'admin';
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Quotation = 'quotation';
    case Vendor = 'vendor';
    case Api = 'api';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Admin => 'Admin',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Whatsapp => 'WhatsApp',
            self::Quotation => 'Quotation',
            self::Vendor => 'Vendor',
            self::Api => 'API',
            self::Mobile => 'Mobile',
        };
    }

    /**
     * Sources staff may pick on the Reservation Desk (admin manual flow).
     *
     * @return array<int, string>
     */
    public static function staffCreatable(): array
    {
        return [
            self::Admin->value,
            self::WalkIn->value,
            self::Phone->value,
            self::Whatsapp->value,
            self::Quotation->value,
        ];
    }
}
