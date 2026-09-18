<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Operational settings for scheduler-driven platform work (11.5D).
 *
 * All keys live in the settings store so CodeCanyon installs need no
 * .env changes. Keep this list small and deliberate — do not add a
 * toggle per business whim.
 */
class OperationsSettings
{
    public const DEFAULTS = [
        // Master switch for all scheduler-driven reminders.
        'ops.reminders_enabled' => '1',
        // Follow-up reminders (assigned-staff in-app notices).
        'ops.followup_reminders_enabled' => '1',
        // Quotation expiry automation + expiring-soon window (days).
        'ops.quotation_expiry_enabled' => '1',
        'ops.quotation_expiry_reminder_days' => '3',
        // Payment due reminders: comma-separated offsets in days before
        // due date, e.g. "3,1,0". Empty disables automatic sends;
        // staff can always trigger a manual reminder.
        'ops.payment_reminder_offsets' => '3,1,0',
        // Travel reminders: days-before offsets for customer + vendor.
        'ops.travel_reminder_customer_offsets' => '3,1',
        'ops.travel_reminder_vendor_offsets' => '3,1',
        // Campaign scheduler master switch.
        'ops.campaigns_scheduled_enabled' => '1',
        // Admin digest: off|daily|weekly.
        'ops.admin_digest_frequency' => 'off',
        // Opt-in admin alert for manually created CRM leads.
        'ops.notify_lead_created' => '0',
        // Retention (days). Notifications only — financial/audit records
        // are never auto-deleted.
        'ops.notification_retention_days' => '180',
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

    /** @return array<int, int> */
    public static function intOffsets(string $key): array
    {
        $raw = (string) (self::get($key) ?? '');
        $offsets = [];

        foreach (explode(',', $raw) as $part) {
            $part = trim($part);

            if ($part === '' || ! is_numeric($part)) {
                continue;
            }

            $offsets[] = max(0, (int) $part);
        }

        return array_values(array_unique($offsets));
    }
}
