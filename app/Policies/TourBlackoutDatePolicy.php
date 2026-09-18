<?php

namespace App\Policies;

use App\Models\TourBlackoutDate;
use App\Models\TourPackage;
use App\Models\User;

class TourBlackoutDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor();
    }

    public function view(User $user, TourBlackoutDate $blackout): bool
    {
        return $this->managesTour($user, $blackout->tour);
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

    public function update(User $user, TourBlackoutDate $blackout): bool
    {
        return $this->managesTour($user, $blackout->tour);
    }

    public function delete(User $user, TourBlackoutDate $blackout): bool
    {
        return $this->managesTour($user, $blackout->tour);
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
