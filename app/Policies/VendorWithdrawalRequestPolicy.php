<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorWithdrawalRequest;

/**
 * Withdrawal request authorization.
 *
 * Vendors create/cancel only their own profile's requests (KYC and balance
 * eligibility live in VendorLedgerService, not here). Review actions
 * (approve/reject/mark-paid) are admin-only, so a vendor can never approve
 * their own request. Customers see nothing.
 */
class VendorWithdrawalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isVendor() && $user->vendorProfile()->exists());
    }

    public function view(User $user, VendorWithdrawalRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->owns($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->isVendor() && $user->vendorProfile()->exists();
    }

    public function cancel(User $user, VendorWithdrawalRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->owns($user, $request);
    }

    public function review(User $user, VendorWithdrawalRequest $request): bool
    {
        return $user->isAdmin();
    }

    protected function owns(User $user, VendorWithdrawalRequest $request): bool
    {
        if (! $user->isVendor()) {
            return false;
        }

        $profileId = $user->vendorProfile?->id;

        return $profileId !== null && (int) $request->vendor_profile_id === (int) $profileId;
    }
}
