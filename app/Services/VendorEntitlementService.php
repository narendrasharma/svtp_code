<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\TourAddon;
use App\Models\TourPackage;
use App\Models\VendorPlan;
use App\Models\VendorPlanAssignment;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor plan entitlements (Phase 11).
 *
 * Single home for every "can this vendor do X?" decision. Controllers must
 * never branch on `$plan->slug === 'pro'` — they call these helpers so
 * future billing/subscription changes stay in one place.
 *
 * Limit semantics:
 * - `max_active_tours` counts APPROVED + ACTIVE tours only (drafts,
 *   pending, rejected never count). Vendors may always draft; submission
 *   is the enforcing gate.
 * - `max_coupons` counts ACTIVE coupons (deactivating frees a slot).
 * - `max_addons_per_tour` counts ALL add-ons on the tour.
 * - Downgrades never delete content; over-limit vendors are blocked only
 *   from new/activating actions until usage falls below the limit.
 * - `unlimited` value_type (or missing feature) means no cap.
 */
class VendorEntitlementService
{
    public function planFor(VendorProfile $profile): ?VendorPlan
    {
        if ($profile->relationLoaded('plan') && $profile->plan !== null) {
            $profile->plan->loadMissing('features');

            return $profile->plan;
        }

        return VendorPlan::with('features')->find($profile->vendor_plan_id);
    }

    public function defaultPlan(): ?VendorPlan
    {
        return VendorPlan::with('features')->where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Assign the configured default plan to vendors without one (new
     * approvals + safety net). No-op when no default exists.
     */
    public function ensureDefaultPlan(VendorProfile $profile, ?int $assignedBy = null): ?VendorPlan
    {
        $profile->refresh();

        if ($profile->vendor_plan_id !== null) {
            return $this->planFor($profile);
        }

        $default = $this->defaultPlan();

        if ($default === null) {
            return null;
        }

        $this->assignPlan($profile->refresh(), $default, $assignedBy, 'Default plan on approval.');

        return $default;
    }

    /**
     * Manual admin assignment (no payment in Phase 11). Inactive plans
     * can never become a NEW assignment; historical rows remain.
     *
     * @throws ValidationException
     */
    public function assignPlan(VendorProfile $profile, VendorPlan $plan, ?int $assignedBy = null, ?string $note = null): VendorPlanAssignment
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['vendor_plan_id' => 'Inactive plans cannot be assigned to vendors.']);
        }

        return DB::transaction(function () use ($profile, $plan, $assignedBy, $note): VendorPlanAssignment {
            $assignment = VendorPlanAssignment::create([
                'vendor_profile_id' => $profile->id,
                'vendor_plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => null,
                'assigned_by' => $assignedBy,
                'note' => $note,
            ]);

            $profile->update(['vendor_plan_id' => $plan->id]);

            return $assignment;
        });
    }

    /**
     * Integer cap or null when unlimited/missing/non-integer.
     */
    public function limitFor(VendorProfile $profile, string $key): ?int
    {
        $feature = $this->planFor($profile)?->feature($key);

        if ($feature === null || $feature->isUnlimited()) {
            return null;
        }

        if ($feature->value_type !== 'integer' || $feature->integer_value === null) {
            return null;
        }

        return max(0, (int) $feature->integer_value);
    }

    public function booleanFor(VendorProfile $profile, string $key, bool $default = false): bool
    {
        $feature = $this->planFor($profile)?->feature($key);

        if ($feature === null || $feature->isUnlimited()) {
            return $default;
        }

        if ($feature->value_type !== 'boolean' || $feature->boolean_value === null) {
            return $default;
        }

        return (bool) $feature->boolean_value;
    }

    public function stringFor(VendorProfile $profile, string $key, ?string $default = null): ?string
    {
        $feature = $this->planFor($profile)?->feature($key);

        if ($feature === null || $feature->isUnlimited()) {
            return $default;
        }

        if ($feature->value_type !== 'string') {
            return $default;
        }

        return $feature->string_value ?? $default;
    }

    public function activeTourUsage(VendorProfile $profile): int
    {
        return TourPackage::where('vendor_profile_id', $profile->id)
            ->where('is_active', true)
            ->where('moderation_status', 'approved')
            ->count();
    }

    public function couponUsage(VendorProfile $profile): int
    {
        return Coupon::where('vendor_profile_id', $profile->id)->where('is_active', true)->count();
    }

    public function addonUsage(TourPackage $tour): int
    {
        return TourAddon::where('tour_package_id', $tour->id)->count();
    }

    /**
     * @return array{allowed: bool, reason: ?string, usage: int, limit: ?int}
     */
    public function canSubmitTour(VendorProfile $profile): array
    {
        $limit = $this->limitFor($profile, VendorPlan::KEY_MAX_ACTIVE_TOURS);
        $usage = $this->activeTourUsage($profile);

        if ($limit !== null && $usage >= $limit) {
            return [
                'allowed' => false,
                'reason' => "Your plan allows {$limit} active tour(s). You have {$usage} — deactivate or wait for review before submitting more.",
                'usage' => $usage,
                'limit' => $limit,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'usage' => $usage, 'limit' => $limit];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSubmitTour(VendorProfile $profile): void
    {
        $check = $this->canSubmitTour($profile);

        if (! $check['allowed']) {
            throw ValidationException::withMessages(['tour' => $check['reason']]);
        }
    }

    /**
     * @return array{allowed: bool, reason: ?string, usage: int, limit: ?int}
     */
    public function canCreateCoupon(VendorProfile $profile): array
    {
        $limit = $this->limitFor($profile, VendorPlan::KEY_MAX_COUPONS);
        $usage = $this->couponUsage($profile);

        if ($limit !== null && $usage >= $limit) {
            return [
                'allowed' => false,
                'reason' => "Your plan allows {$limit} active coupon(s). Deactivate one before creating another.",
                'usage' => $usage,
                'limit' => $limit,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'usage' => $usage, 'limit' => $limit];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreateCoupon(VendorProfile $profile): void
    {
        $check = $this->canCreateCoupon($profile);

        if (! $check['allowed']) {
            throw ValidationException::withMessages(['code' => $check['reason']]);
        }
    }

    /**
     * @return array{allowed: bool, reason: ?string, usage: int, limit: ?int}
     */
    public function canCreateAddon(TourPackage $tour): array
    {
        $profile = $tour->vendorProfile;

        if ($profile === null) {
            return ['allowed' => true, 'reason' => null, 'usage' => $this->addonUsage($tour), 'limit' => null];
        }

        $limit = $this->limitFor($profile, VendorPlan::KEY_MAX_ADDONS_PER_TOUR);
        $usage = $this->addonUsage($tour);

        if ($limit !== null && $usage >= $limit) {
            return [
                'allowed' => false,
                'reason' => "Your plan allows {$limit} add-on(s) per tour.",
                'usage' => $usage,
                'limit' => $limit,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'usage' => $usage, 'limit' => $limit];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreateAddon(TourPackage $tour): void
    {
        $check = $this->canCreateAddon($tour);

        if (! $check['allowed']) {
            throw ValidationException::withMessages(['name' => $check['reason']]);
        }
    }

    public function canUseFeaturedListing(VendorProfile $profile): bool
    {
        return $this->booleanFor($profile, VendorPlan::KEY_FEATURED_LISTING, false);
    }

    public function canUseStorefront(VendorProfile $profile): bool
    {
        return $this->booleanFor($profile, VendorPlan::KEY_STOREFRONT_ENABLED, true);
    }

    /**
     * @return array{tours: array{usage: int, limit: ?int}, coupons: array{usage: int, limit: ?int}}
     */
    public function usageSummary(VendorProfile $profile): array
    {
        return [
            'tours' => [
                'usage' => $this->activeTourUsage($profile),
                'limit' => $this->limitFor($profile, VendorPlan::KEY_MAX_ACTIVE_TOURS),
            ],
            'coupons' => [
                'usage' => $this->couponUsage($profile),
                'limit' => $this->limitFor($profile, VendorPlan::KEY_MAX_COUPONS),
            ],
        ];
    }
}
