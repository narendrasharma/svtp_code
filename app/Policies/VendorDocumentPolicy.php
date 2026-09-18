<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorDocument;

class VendorDocumentPolicy
{
    public function view(User $user, VendorDocument $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $verification = $document->verification;

        return $verification && (int) $verification->user_id === (int) $user->id;
    }

    public function update(User $user, VendorDocument $document): bool
    {
        // Only owner can replace own pending/rejected document, not verified ones arbitrarily
        if ($user->isAdmin()) {
            return false;
        }

        $verification = $document->verification;

        return $verification && (int) $verification->user_id === (int) $user->id;
    }

    public function verify(User $user, VendorDocument $document): bool
    {
        return $user->isAdmin();
    }
}
