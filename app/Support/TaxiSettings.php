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
        'taxi.cancellation.enabled' => '1',
        'taxi.customer_cancellation.enabled' => '0',
        'taxi.reschedule.enabled' => '1',
        'taxi.customer_reschedule.enabled' => '0',
        'taxi.refunds.enabled' => '1',
        'taxi.cancellation.default_reason_required' => '1',
        'taxi.booking_enabled' => '1',
        'taxi.one_way_enabled' => '1',
        'taxi.airport_transfer_enabled' => '1',
        'taxi.default_currency' => 'INR',
        'taxi.min_advance_minutes' => '60',
        'taxi.max_advance_days' => '',
        'taxi.allow_guest_booking' => '1',
        'taxi.default_assignment_mode' => 'manual',
        'taxi.tracking_stale_seconds' => '120',
        'taxi.location_retention_days' => '30',
        'taxi.maps.provider' => 'none',
        'taxi.maps.enabled' => '0',
        'taxi.maps.google.browser_key' => '',
        'taxi.maps.google.server_key' => '',
        'taxi.maps.mapbox.public_token' => '',
        'taxi.maps.mapbox.server_token' => '',
        'taxi.routing.enabled' => '0',
        'taxi.routing.cache_minutes' => '10',
        'taxi.routing.refresh_seconds' => '60',
        'taxi.dispatch.smart_enabled' => '1',
        'taxi.dispatch.max_pickup_radius_km' => '0',
        'taxi.dispatch.routing_candidate_limit' => '5',
        'taxi.dispatch.use_routing_eta' => '1',
        'taxi.dispatch.auto_enabled' => '0',
        'taxi.dispatch.offer_enabled' => '1',
        'taxi.dispatch.offer_timeout_seconds' => '120',
        'taxi.dispatch.max_offer_attempts' => '3',
        'taxi.dispatch.require_driver_acceptance' => '1',
        'taxi.dispatch.auto_fallback_manual' => '1',
        'taxi.customer_tracking.enabled' => '0',
        'taxi.customer_tracking.token_expiry_hours' => '',
        'taxi.customer_tracking.show_driver_phone' => '0',
        'taxi.customer_tracking.show_vehicle_registration' => '0',
        'taxi.customer_tracking.show_route' => '1',
        'taxi.customer_tracking.refresh_seconds' => '30',
        'taxi.driver_earnings.auto_payable_on_complete' => '1',
        'taxi.driver_earnings.hold_days' => '0',
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

    public static function earningsAutoPayable(): bool
    {
        return in_array(strtolower((string) self::get('taxi.driver_earnings.auto_payable_on_complete')), ['1', 'true', 'yes', 'on'], true);
    }

    public static function earningsHoldDays(): int
    {
        return max(0, (int) (self::get('taxi.driver_earnings.hold_days') ?? 0));
    }
}
