<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verified booking review eligibility (Phase 9).
 *
 * A customer may review a tour only through a completed booking they own:
 * same tour, completed status, not cancelled, not fully refunded, and no
 * review yet for that booking. Guest reviews (booking_id null) keep working
 * through the public form and never earn the verified badge. Moderation is
 * untouched: verified means purchase-verified, never auto-approved.
 */
class ReviewService
{
    public function __construct(protected BookingRefundService $refunds) {}

    /**
     * Eligibility scoped to one booking (account booking page).
     *
     * @return array{can_review: bool, booking_id: ?int, has_review: bool, review_id: ?int, reason: ?string}
     */
    public function bookingEligibility(User $user, Booking $booking): array
    {
        if ($booking->user_id === null || (int) $booking->user_id !== (int) $user->id) {
            return $this->denied($booking, 'Only your own bookings can be reviewed.');
        }

        if ($booking->booking_status !== BookingStatus::Completed) {
            return $this->denied($booking, 'Reviews open after a completed tour.');
        }

        if ($booking->payment_status !== PaymentStatus::Paid) {
            return $this->denied($booking, 'Only paid bookings can be reviewed.');
        }

        if (VendorLedgerService::toPaise($this->refunds->refundableRemaining($booking)) <= 0) {
            return $this->denied($booking, 'Fully refunded bookings cannot be reviewed.');
        }

        $existing = $booking->review;

        if ($existing) {
            return [
                'can_review' => false,
                'booking_id' => $booking->id,
                'has_review' => true,
                'review_id' => $existing->id,
                'reason' => 'You already reviewed this booking.',
            ];
        }

        return [
            'can_review' => true,
            'booking_id' => $booking->id,
            'has_review' => false,
            'review_id' => null,
            'reason' => null,
        ];
    }

    /**
     * @return array{can_review: bool, booking_id: ?int, has_review: bool, review_id: ?int, reason: ?string}
     */
    protected function denied(Booking $booking, string $reason): array
    {
        return [
            'can_review' => false,
            'booking_id' => $booking->id,
            'has_review' => false,
            'review_id' => null,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{can_review: bool, booking_id: ?int, has_review: bool, review_id: ?int, reason: ?string}
     */
    public function eligibility(User $user, TourPackage $package): array
    {
        $booking = $this->eligibleBooking($user, $package);

        if ($booking) {
            $existing = $booking->review;

            return [
                'can_review' => $existing === null,
                'booking_id' => $booking->id,
                'has_review' => $existing !== null,
                'review_id' => $existing?->id,
                'reason' => $existing ? 'You already reviewed this booking.' : null,
            ];
        }

        return [
            'can_review' => false,
            'booking_id' => $this->latestBlockingBooking($user, $package)?->id,
            'has_review' => false,
            'review_id' => null,
            'reason' => 'Reviews open after a completed tour.',
        ];
    }

    public function eligibleBooking(User $user, TourPackage $package): ?Booking
    {
        return Booking::where('user_id', $user->id)
            ->where('package_id', $package->id)
            ->where('booking_status', BookingStatus::Completed->value)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->latest('id')
            ->get()
            ->first(function (Booking $booking): bool {
                if ($booking->review()->exists()) {
                    return false;
                }

                // Fully refunded tours cannot be reviewed; partials still can.
                return VendorLedgerService::toPaise($this->refunds->refundableRemaining($booking)) > 0;
            });
    }

    /**
     * The most relevant owned booking for UX messaging (may be ineligible).
     */
    protected function latestBlockingBooking(User $user, TourPackage $package): ?Booking
    {
        return Booking::where('user_id', $user->id)
            ->where('package_id', $package->id)
            ->latest('id')
            ->first();
    }

    /**
     * Submit a verified booking review. Guarded end-to-end (policy +
     * eligibility + unique booking_id) so normal public forms can never
     * manufacture a verified review.
     *
     * @param  array{rating: int, comment?: ?string}  $data
     */
    public function submitBookingReview(User $user, Booking $booking, array $data): Review
    {
        if ($booking->user_id === null || (int) $booking->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['booking' => 'You can only review your own bookings.']);
        }

        if ($booking->booking_status !== BookingStatus::Completed) {
            throw ValidationException::withMessages(['booking' => 'Only completed tours can be reviewed.']);
        }

        if ($booking->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages(['booking' => 'Only paid bookings can be reviewed.']);
        }

        if (VendorLedgerService::toPaise($this->refunds->refundableRemaining($booking)) <= 0) {
            throw ValidationException::withMessages(['booking' => 'Fully refunded bookings cannot be reviewed.']);
        }

        if ($booking->review()->exists()) {
            throw ValidationException::withMessages(['booking' => 'This booking already has a review.']);
        }

        return DB::transaction(fn (): Review => $booking->review()->create([
            'user_id' => $user->id,
            'reviewer_name' => $user->name,
            'reviewer_email' => $user->email,
            'package_id' => $booking->package_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'is_approved' => false,
        ]));
    }
}
