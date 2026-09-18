<?php

namespace App\Policies;

use App\Models\TourAddon;
use App\Models\TourPackage;
use App\Models\User;

class TourAddonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor();
    }

    public function view(User $user, TourAddon $addon): bool
    {
        return $this->managesTour($user, $addon->tour);
    }

    public function create(User $user, ?TourPackage $tour = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isVendor() && $tour !== null) {
            return $tour->isOwnedByVendorProfile($user->vendorProfile);
        }

        return $user->isVendor();
    }

    public function update(User $user, TourAddon $addon): bool
    {
        return $this->managesTour($user, $addon->tour);
    }

    public function delete(User $user, TourAddon $addon): bool
    {
        return $this->managesTour($user, $addon->tour);
    }

    protected function managesTour(User $user, ?TourPackage $tour): bool
    {
        if ($tour === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isVendor()) {
            return $tour->isOwnedByVendorProfile($user->vendorProfile);
        }

        return false;
    }
}
