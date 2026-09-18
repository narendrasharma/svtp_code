<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor();
    }

    public function view(User $user, Coupon $coupon): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isVendor()) {
            return $coupon->isOwnedByVendorProfile($user->vendorProfile);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor();
    }

    public function update(User $user, Coupon $coupon): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isVendor()) {
            return $coupon->isOwnedByVendorProfile($user->vendorProfile);
        }

        return false;
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        // Ownership only — redemption history is handled in controllers
        // with a friendly "deactivate instead" error, not a 403.
        return $this->update($user, $coupon);
    }
}
