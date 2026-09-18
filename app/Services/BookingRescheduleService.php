<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingReschedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Date-change workflow. The booking row is only rewritten after the new
 * date passes every availability rule; old dates and both totals stay in
 * the reschedule row forever. Totals move with the change (increase →
 * more due; decrease → smaller due, possibly credit) but no refund or
 * payment is ever issued automatically — those stay explicit actions.
 */
class BookingRescheduleService
{
    public function __construct(
        protected TourBookingPricingService $pricing,
        protected TourAvailabilityService $availability,
        protected BookingService $bookings,
    ) {}

    public function reschedule(
        Booking $booking,
        string $newTravelDate,
        string $reason,
        ?User $actor = null,
    ): BookingReschedule {
        if (! in_array($booking->booking_status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            throw ValidationException::withMessages(['travel_date' => 'Only pending or confirmed bookings can be rescheduled.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required — it stays on the booking timeline.']);
        }

        $package = $booking->package;

        if (! $package) {
            throw ValidationException::withMessages(['travel_date' => 'This booking has no tour attached.']);
        }

        // Every availability rule (active/approved, booking window,
        // weekdays, advance limits, blackout dates) — server-enforced.
        $this->availability->validateBookingDate($package->refresh(), $newTravelDate);

        if ($newTravelDate === $booking->travel_date->toDateString()) {
            throw ValidationException::withMessages(['travel_date' => 'The new date is the same as the current travel date.']);
        }

        return DB::transaction(function () use ($booking, $package, $newTravelDate, $reason, $actor): BookingReschedule {
            $oldDate = $booking->travel_date->toDateString();
            $oldTotal = round((float) $booking->total_amount, 2);

            // Recompute the tour base for the same party; stored add-on,
            // coupon and tax snapshots carry over untouched.
            $base = $this->pricing->quote($package, (int) $booking->total_adults, (int) $booking->total_children);
            $newTotal = round(
                $base['subtotal']
                + (float) $booking->addons_total
                - (float) $booking->discount_amount
                + (float) $booking->tax_amount,
                2
            );

            if ($newTotal < 0) {
                $newTotal = 0.0;
            }

            $difference = round($newTotal - $oldTotal, 2);

            $reschedule = $booking->reschedules()->create([
                'change_type' => BookingReschedule::TYPE_DATE_CHANGE,
                'old_travel_date' => $oldDate,
                'new_travel_date' => $newTravelDate,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'price_difference' => $difference,
                'reason' => mb_substr(trim($reason), 0, 2000),
                'requested_by' => $actor?->id,
                'approved_by' => $actor?->id,
                'status' => BookingReschedule::STATUS_APPROVED,
            ]);

            $booking->update(['travel_date' => $newTravelDate, 'total_amount' => $newTotal]);

            $money = $difference > 0
                ? 'Additional ₹'.number_format($difference, 2).' now due.'
                : ($difference < 0
                    ? '₹'.number_format(abs($difference), 2).' reduced — collect a smaller balance; no automatic refund issued.'
                    : 'No price change.');

            $this->bookings->logNote(
                $booking->refresh(),
                $actor,
                "Rescheduled {$oldDate} → {$newTravelDate}. Total ₹".number_format($oldTotal, 2).' → ₹'.number_format($newTotal, 2).'. Reason: '.mb_substr(trim($reason), 0, 500)." {$money}",
                true
            );

            return $reschedule->refresh();
        });
    }
}
