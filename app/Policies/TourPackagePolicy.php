<?php

namespace App\Policies;

use App\Models\TourPackage;
use App\Models\User;

class TourPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor();
    }

    public function view(User $user, TourPackage $package): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isVendor()) {
            return $package->vendor_profile_id && $user->vendorProfile && (int) $package->vendor_profile_id === (int) $user->vendorProfile->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isVendor() || $user->isAdmin();
    }

    public function update(User $user, TourPackage $package): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isVendor()) {
            return $package->vendor_profile_id && $user->vendorProfile && (int) $package->vendor_profile_id === (int) $user->vendorProfile->id;
        }

        return false;
    }

    public function submitForReview(User $user, TourPackage $package): bool
    {
        return $this->update($user, $package);
    }

    public function delete(User $user, TourPackage $package): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isVendor() && $this->update($user, $package)) {
            // Only draft with no bookings can be deleted
            if ($package->moderation_status->value !== 'draft') {
                return false;
            }

            return $package->bookings()->count() === 0;
        }

        return false;
    }
}
