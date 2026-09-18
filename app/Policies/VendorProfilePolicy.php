<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorProfile;

class VendorProfilePolicy
{
    public function view(User $user, VendorProfile $profile): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (int) $profile->user_id === (int) $user->id;
    }

    public function update(User $user, VendorProfile $profile): bool
    {
        if ($user->isAdmin()) {
            // Admin edits via different flow; vendor profile policy restricts to owner for vendor dashboard
            return true;
        }

        return (int) $profile->user_id === (int) $user->id;
    }
}
