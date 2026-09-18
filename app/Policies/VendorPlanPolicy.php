<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorPlan;

class VendorPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, VendorPlan $plan): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, VendorPlan $plan): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, VendorPlan $plan): bool
    {
        return $user->isAdmin();
    }

    public function assign(User $user): bool
    {
        return $user->isAdmin();
    }
}
