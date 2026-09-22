<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Hotel module settings foundation (12B.1).
 *
 * Mirrors the TaxiSettings pattern: small deliberate list stored in the
 * shared settings store, centralized defaults so absent settings never
 * fatal. Module on/off itself lives in ModuleManager (modules.hotels).
 */
class HotelSettings
{
    public const DEFAULTS = [
        'hotel.vendor_can_create_properties' => '1',
        'hotel.require_property_approval' => '1',
        'hotel.default_currency' => 'USD',
        'hotel.default_timezone' => '',
        'hotel.properties_per_page' => '12',
        'hotel.show_contact_details' => '1',
        'hotel.rooms.show_size' => '1',
        'hotel.rooms.show_bed_details' => '1',
        'hotel.rooms.units_enabled' => '1',
        'hotel.inventory.max_bulk_days' => '365',
        'hotel.availability.max_stay_nights' => '30',
        'hotel.availability.public_check_enabled' => '1',
        'hotel.pricing.max_bulk_days' => '365',
        'hotel.pricing.show_tax_breakdown' => '1',
        'hotel.pricing.show_nightly_breakdown' => '1',
        'hotel.booking.enabled' => '1',
        'hotel.booking.require_login' => '1',
        'hotel.booking.allow_guest_booking' => '0',
        'hotel.booking.default_status' => 'confirmed',
        'hotel.booking.max_rooms_per_booking' => '10',
        'hotel.booking.require_terms_acceptance' => '0',
        'hotel.cancellation.enabled' => '1',
        'hotel.reschedule.enabled' => '1',
        'hotel.reschedule.minimum_notice_hours' => '24',
        'hotel.refund.auto_create_accounting_record' => '1',
        'hotel.reviews.enabled' => '1',
        'hotel.reviews.moderation_enabled' => '1',
        'hotel.reviews.minimum_comment_length' => '20',
        'hotel.reviews.vendor_replies_enabled' => '1',
    ];

    public static function get(string $key): ?string
    {
        $value = Setting::getValue($key, null);

        if ($value === null || $value === '') {
            return self::DEFAULTS[$key] ?? null;
        }

        return (string) $value;
    }

    public static function enabled(string $key): bool
    {
        return in_array(strtolower((string) self::get($key)), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        $out = [];

        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = (string) (Setting::getValue($key, null) ?? $default);
        }

        return $out;
    }

    public static function defaultCurrency(): string
    {
        $raw = strtoupper((string) (self::get('hotel.default_currency') ?? ''));

        return preg_match('/^[A-Z]{3}$/', $raw) === 1 ? $raw : 'USD';
    }

    public static function defaultTimezone(): string
    {
        $raw = (string) (self::get('hotel.default_timezone') ?? '');

        return in_array($raw, timezone_identifiers_list(), true) ? $raw : config('app.timezone', 'UTC');
    }

    public static function perPage(): int
    {
        return min(48, max(6, (int) (self::get('hotel.properties_per_page') ?? 12)));
    }

    public static function maxBulkDays(): int
    {
        return min(730, max(1, (int) (self::get('hotel.inventory.max_bulk_days') ?? 365)));
    }

    public static function maxStayNights(): int
    {
        return min(90, max(1, (int) (self::get('hotel.availability.max_stay_nights') ?? 30)));
    }

    public static function minimumReviewLength(): int
    {
        return min(500, max(10, (int) self::get('hotel.reviews.minimum_comment_length')));
    }
}
