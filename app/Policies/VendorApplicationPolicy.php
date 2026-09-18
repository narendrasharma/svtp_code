<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorApplication;

class VendorApplicationPolicy
{
    public function view(User $user, VendorApplication $application): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (int) $application->user_id === (int) $user->id;
    }

    public function update(User $user, VendorApplication $application): bool
    {
        // Only owner can update while pending / resubmission requested
        if ($user->isAdmin()) {
            return false;
        }

        if ((int) $application->user_id !== (int) $user->id) {
            return false;
        }

        return in_array($application->status->value, ['pending', 'resubmission_requested'], true);
    }

    public function approve(User $user, VendorApplication $application): bool
    {
        return $user->isAdmin();
    }
}
