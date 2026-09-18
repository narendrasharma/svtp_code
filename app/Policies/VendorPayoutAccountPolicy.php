<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorPayoutAccount;

/**
 * Payout destination authorization.
 *
 * Vendors manage only their own account (verification state is
 * server-derived — a vendor can never self-verify). Admins review all.
 * Customers see nothing. Secrets are never exposed: controllers return
 * toSafeArray() (masked) for every role including admin.
 */
class VendorPayoutAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isVendor() && $user->vendorProfile()->exists());
    }

    public function view(User $user, VendorPayoutAccount $account): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->owns($user, $account);
    }

    public function save(User $user): bool
    {
        return $user->isVendor() && $user->vendorProfile()->exists();
    }

    public function review(User $user, VendorPayoutAccount $account): bool
    {
        return $user->isAdmin();
    }

    protected function owns(User $user, VendorPayoutAccount $account): bool
    {
        if (! $user->isVendor()) {
            return false;
        }

        $profileId = $user->vendorProfile?->id;

        return $profileId !== null && (int) $account->vendor_profile_id === (int) $profileId;
    }
}
