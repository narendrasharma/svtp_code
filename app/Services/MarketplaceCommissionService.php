<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TourPackage;

/**
 * Marketplace commission domain service.
 *
 * Single home for the platform/vendor money split. Controllers, Vue and
 * models must never compute commission splits — BookingService calls
 * snapshotForTour() at booking creation and stores the immutable result.
 *
 * Money math uses integer minor units (paise), so the invariant
 *
 *     gross_amount = platform_commission_amount + vendor_earning_amount
 *
 * holds exactly. Percentages support up to 2 decimals.
 *
 * One global default rate only (Phase 6). Per-vendor / per-tour / category
 * rates can be added later behind resolvePercentage() without touching
 * callers. Future Taxi/Hotel modules get sibling snapshot entry points
 * here instead of a new service.
 */
class MarketplaceCommissionService
{
    public const SETTING_KEY = 'platform_commission_percentage';

    /**
     * Fallback rate (%) when the setting is missing or invalid.
     */
    public const FALLBACK_PERCENTAGE = '10.00';

    public function defaultPercentage(): string
    {
        return self::normalizePercentage(Setting::getValue(self::SETTING_KEY));
    }

    /**
     * Normalize raw setting input to a 2-decimal percentage string.
     */
    public static function normalizePercentage(mixed $raw): string
    {
        if (! is_numeric($raw)) {
            return self::FALLBACK_PERCENTAGE;
        }

        $value = (float) $raw;

        if ($value < 0 || $value > 100) {
            return self::FALLBACK_PERCENTAGE;
        }

        return number_format(round($value, 2), 2, '.', '');
    }

    /**
     * Snapshot the responsible vendor and the commission split for a tour
     * booking. Uses the CURRENT default rate — stored snapshots are never
     * recalculated, so later setting changes only affect future bookings.
     *
     * @return array{vendor_profile_id: ?int, gross_amount: string, platform_commission_percentage: ?string, platform_commission_amount: string, vendor_earning_amount: string}
     */
    public function snapshotForTour(TourPackage $package, float|string $gross): array
    {
        $vendorProfileId = $package->vendor_profile_id !== null ? (int) $package->vendor_profile_id : null;
        $grossDecimal = self::toDecimal($gross);

        if ($vendorProfileId === null) {
            // Admin-owned tour: no vendor assignment, no vendor earning —
            // the platform retains the full booking value.
            return [
                'vendor_profile_id' => null,
                'gross_amount' => $grossDecimal,
                'platform_commission_percentage' => null,
                'platform_commission_amount' => '0.00',
                'vendor_earning_amount' => '0.00',
            ];
        }

        $percentage = $this->defaultPercentage();
        $split = self::splitAmounts($grossDecimal, $percentage);

        return [
            'vendor_profile_id' => $vendorProfileId,
            'gross_amount' => $grossDecimal,
            'platform_commission_percentage' => $percentage,
            'platform_commission_amount' => $split['commission'],
            'vendor_earning_amount' => $split['earning'],
        ];
    }

    /**
     * Exact half-up split computed in integer paise.
     *
     * @return array{commission: string, earning: string} 2-decimal strings, earning = gross - commission.
     */
    public static function splitAmounts(string $gross, string $percentage): array
    {
        $grossPaise = (int) round(((float) $gross) * 100);
        $rateBasisPoints = (int) round(((float) $percentage) * 100);
        $commissionPaise = intdiv($grossPaise * $rateBasisPoints + 5000, 10000);
        $earningPaise = $grossPaise - $commissionPaise;

        return [
            'commission' => number_format($commissionPaise / 100, 2, '.', ''),
            'earning' => number_format($earningPaise / 100, 2, '.', ''),
        ];
    }

    public static function toDecimal(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
