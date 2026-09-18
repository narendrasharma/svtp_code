<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorLedgerEntry;

/**
 * Vendor ledger visibility.
 *
 * Admins see everything; vendors see only rows for their own profile;
 * customers see nothing. Ledger rows are immutable, so no update/delete
 * ability exists — adjustments are append-only via VendorLedgerService.
 */
class VendorLedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isVendor() && $user->vendorProfile()->exists());
    }

    public function view(User $user, VendorLedgerEntry $entry): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isVendor()) {
            return false;
        }

        $profileId = $user->vendorProfile?->id;

        return $profileId !== null && (int) $entry->vendor_profile_id === (int) $profileId;
    }
}
