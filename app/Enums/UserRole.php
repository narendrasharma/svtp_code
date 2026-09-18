<?php

namespace App\Enums;

/**
 * Central user roles.
 *
 * Admin, customer and vendor. Vendor is a customer promoted through
 * VendorApplication approval — no scattered role strings.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Customer = 'customer';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Customer => 'Customer',
            self::Vendor => 'Vendor',
        };
    }

    public function isVendor(): bool
    {
        return $this === self::Vendor;
    }
}
