<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Booking authorization, ready for customers, vendors and admins.
 *
 * Admins manage everything; customers see their own bookings (guest bookings
 * without an owner stay admin-visible until a token-based lookup ships).
 * Vendors see only bookings historically assigned to their vendor profile
 * (bookings.vendor_profile_id) — never admin-owned or other vendors' rows.
 */
class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isVendor() && $user->vendorProfile()->exists();
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isVendor()) {
            $profileId = $user->vendorProfile?->id;

            return $profileId !== null
                && $booking->vendor_profile_id !== null
                && (int) $booking->vendor_profile_id === (int) $profileId;
        }

        return $booking->user_id !== null && (int) $booking->user_id === (int) $user->id;
    }

    /**
     * Invoice access mirrors booking visibility today; kept as its own
     * ability so future invoice rules can diverge safely.
     */
    public function viewInvoice(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }

    /**
     * Who may ask for cancellation. Eligibility (status, duplicates) is
     * enforced by CancellationService, not here.
     */
    public function requestCancellation(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || ($booking->user_id !== null && (int) $booking->user_id === (int) $user->id);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }

    /**
     * Bookings are business records — they are cancelled, never deleted.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return false;
    }
}
