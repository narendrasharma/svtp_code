<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coupon domain service (Phase 10).
 *
 * Single home for promo-code validation, discount math and redemption
 * accounting. Controllers and Vue must never compute discounts — the
 * estimate endpoint previews, the booking transaction decides.
 *
 * Calculation order: tour base + add-ons = subtotal → coupon discount →
 * discounted subtotal → + tax (0 today) = total. Commission then splits
 * the discounted gross (coupon reduces gross BEFORE commission;
 * platform-funded vs vendor-funded coupons are deferred).
 *
 * Guest per-user limits use the normalized booking email, never IP.
 */
class CouponService
{
    /**
     * Preview a coupon without persisting anything (estimate endpoint).
     *
     * @return array{coupon: Coupon, discount_amount: float}
     */
    public function preview(
        string $code,
        TourPackage $package,
        float $subtotal,
        ?User $user = null,
        ?string $email = null,
    ): array {
        $coupon = $this->findActive($code);

        if ($coupon === null) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code is not valid.']);
        }

        $this->assertUsable($coupon, $package, $subtotal, $user, $email);

        return [
            'coupon' => $coupon,
            'discount_amount' => $this->discountFor($coupon, $subtotal),
        ];
    }

    /**
     * Authoritative redemption inside the booking transaction. Locks the
     * coupon row so concurrent checkouts cannot overshoot usage_limit.
     *
     * @return array{coupon: Coupon, discount_amount: float}
     */
    public function redeemForBooking(
        string $code,
        TourPackage $package,
        float $subtotal,
        ?User $user = null,
        ?string $email = null,
    ): array {
        return DB::transaction(function () use ($code, $package, $subtotal, $user, $email): array {
            $normalized = Coupon::normalizeCode($code);

            $coupon = Coupon::where('code', $normalized)->lockForUpdate()->first();

            if ($coupon === null || ! $coupon->is_active) {
                throw ValidationException::withMessages(['coupon_code' => 'This promo code is not valid.']);
            }

            $this->assertUsable($coupon, $package, $subtotal, $user, $email, true);

            return [
                'coupon' => $coupon->refresh(),
                'discount_amount' => $this->discountFor($coupon, $subtotal),
            ];
        });
    }

    public function recordRedemption(Coupon $coupon, int $bookingId, ?User $user, ?string $email, float $discount): void
    {
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'booking_id' => $bookingId,
            'user_id' => $user?->id,
            'email' => $email !== null ? strtolower(trim($email)) : null,
            'discount_amount' => number_format($discount, 2, '.', ''),
        ]);
    }

    public function usageCount(Coupon $coupon): int
    {
        return $coupon->redemptions()->count();
    }

    public function usageCountForIdentity(Coupon $coupon, ?User $user, ?string $email): int
    {
        if ($user?->id !== null) {
            return $coupon->redemptions()->where('user_id', $user->id)->count();
        }

        $normalized = strtolower(trim((string) $email));

        if ($normalized === '') {
            return 0;
        }

        return $coupon->redemptions()->whereNull('user_id')->where('email', $normalized)->count();
    }

    /**
     * Discount math: percentage = subtotal × value/100, fixed = value.
     * Always capped by maximum_discount_amount and never above subtotal.
     */
    public function discountFor(Coupon $coupon, float $subtotal): float
    {
        $subtotal = round($subtotal, 2);

        if ($subtotal <= 0) {
            return 0.0;
        }

        if ($coupon->isPercentage()) {
            $discount = round($subtotal * ((float) $coupon->discount_value / 100), 2);
        } else {
            $discount = round((float) $coupon->discount_value, 2);
        }

        if ($coupon->maximum_discount_amount !== null) {
            $discount = min($discount, round((float) $coupon->maximum_discount_amount, 2));
        }

        $discount = min($discount, $subtotal);

        return max(0.0, round($discount, 2));
    }

    public function isApplicableToTour(Coupon $coupon, TourPackage $package): bool
    {
        // Vendor coupons only ever apply to their own vendor's tours.
        if ($coupon->vendor_profile_id !== null) {
            if ($package->vendor_profile_id === null) {
                return false;
            }

            if ((int) $package->vendor_profile_id !== (int) $coupon->vendor_profile_id) {
                return false;
            }
        }

        if ($coupon->scope === Coupon::SCOPE_GLOBAL) {
            return true;
        }

        if ($coupon->scope === Coupon::SCOPE_VENDOR) {
            return $coupon->vendor_profile_id !== null
                && $package->vendor_profile_id !== null
                && (int) $package->vendor_profile_id === (int) $coupon->vendor_profile_id;
        }

        // Scope "tours": explicit pivot allow-list.
        return $coupon->tours()->where('tour_packages.id', $package->id)->exists();
    }

    protected function findActive(string $code): ?Coupon
    {
        $normalized = Coupon::normalizeCode($code);

        if ($normalized === '') {
            return null;
        }

        $coupon = Coupon::where('code', $normalized)->first();

        return $coupon !== null && $coupon->is_active ? $coupon : null;
    }

    /**
     * @throws ValidationException
     */
    protected function assertUsable(
        Coupon $coupon,
        TourPackage $package,
        float $subtotal,
        ?User $user,
        ?string $email,
        bool $locked = false,
    ): void {
        $now = now();

        if (! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code is no longer active.']);
        }

        if ($coupon->starts_at !== null && $now->lt($coupon->starts_at)) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code is not yet valid.']);
        }

        if ($coupon->ends_at !== null && $now->gt($coupon->ends_at)) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code has expired.']);
        }

        if ($coupon->minimum_booking_amount !== null && $subtotal < (float) $coupon->minimum_booking_amount) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code needs a higher booking amount.']);
        }

        if (! $this->isApplicableToTour($coupon, $package)) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code is not valid for this tour.']);
        }

        $usageQuery = $locked
            ? $coupon->redemptions()->lockForUpdate()
            : $coupon->redemptions();

        // Global cap (authoritative count comes from redemptions, never a
        // mutable counter column).
        if ($coupon->usage_limit !== null && $usageQuery->count() >= (int) $coupon->usage_limit) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code has reached its usage limit.']);
        }

        if ($coupon->usage_limit_per_user !== null) {
            $usedByIdentity = $this->usageCountForIdentity($coupon, $user, $email);

            if ($usedByIdentity >= (int) $coupon->usage_limit_per_user) {
                throw ValidationException::withMessages(['coupon_code' => 'You have already used this promo code.']);
            }
        }

        // Defensive: a discount may never exceed the eligible subtotal and
        // may never drive the total negative (discountFor already caps).
        if ($this->discountFor($coupon, $subtotal) < 0) {
            throw ValidationException::withMessages(['coupon_code' => 'This promo code is not valid for this booking.']);
        }
    }
}
