<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Taxi module settings foundation (12A.1).
 *
 * Small deliberate list stored in the shared settings store. Pricing
 * rules stay in their own future domain — never here.
 */
class TaxiSettings
{
    public const DEFAULTS = [
        'taxi.booking_enabled' => '1',
        'taxi.one_way_enabled' => '1',
        'taxi.airport_transfer_enabled' => '1',
        'taxi.default_currency' => 'INR',
        'taxi.min_advance_minutes' => '60',
        'taxi.max_advance_days' => '',
        'taxi.allow_guest_booking' => '1',
        'taxi.default_assignment_mode' => 'manual',
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

    public static function minAdvanceMinutes(): int
    {
        return max(0, (int) (self::get('taxi.min_advance_minutes') ?? 60));
    }

    public static function maxAdvanceDays(): ?int
    {
        $raw = self::get('taxi.max_advance_days');

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return max(1, (int) $raw);
    }
}
