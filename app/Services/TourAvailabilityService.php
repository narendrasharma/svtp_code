<?php

namespace App\Services;

use App\Models\TourPackage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Practical tour availability (Phase 10).
 *
 * Single home for "can this tour be booked on this date?". Controllers and
 * Vue must treat this as authoritative — the date picker may grey out
 * dates for convenience, but BookingService re-checks inside its
 * transaction, so frontend state can never override it.
 *
 * Checks: tour approved/active, booking_enabled, date not past, weekday
 * allowed, blackout absent, min/max advance days. Capacity/seats are
 * intentionally NOT modelled here (no clean inventory exists; deferred).
 */
class TourAvailabilityService
{
    /**
     * @return array{bookable: bool, reason: ?string}
     */
    public function check(TourPackage $package, string $date): array
    {
        if (! $package->is_active || $package->moderation_status?->value !== 'approved') {
            return ['bookable' => false, 'reason' => 'This tour is no longer available for booking.'];
        }

        if (! (bool) ($package->booking_enabled ?? true)) {
            return ['bookable' => false, 'reason' => 'Online booking is currently paused for this tour.'];
        }

        $day = $this->parseDate($date);

        if ($day === null) {
            return ['bookable' => false, 'reason' => 'Please choose a valid travel date.'];
        }

        $today = Carbon::today();

        if ($day->lt($today)) {
            return ['bookable' => false, 'reason' => 'Travel date cannot be in the past.'];
        }

        $weekdays = $package->available_weekdays;

        if (is_array($weekdays) && $weekdays !== []) {
            $allowed = array_map('intval', $weekdays);

            if (! in_array((int) $day->dayOfWeek, $allowed, true)) {
                return ['bookable' => false, 'reason' => 'This tour is not available on the selected weekday.'];
            }
        }

        $minAdvance = (int) ($package->min_advance_days ?? 0);
        $advanceDays = (int) $today->diffInDays($day, true);

        if ($minAdvance > 0 && $advanceDays < $minAdvance) {
            return ['bookable' => false, 'reason' => "This tour needs at least {$minAdvance} day(s) advance booking."];
        }

        if ($package->max_advance_days !== null) {
            $maxAdvance = (int) $package->max_advance_days;

            if ($advanceDays > $maxAdvance) {
                return ['bookable' => false, 'reason' => "This tour can be booked at most {$maxAdvance} day(s) in advance."];
            }
        }

        $isBlackout = $package->blackoutDates()->whereDate('date', $day->toDateString())->exists();

        if ($isBlackout) {
            return ['bookable' => false, 'reason' => 'The selected travel date is not available for this tour.'];
        }

        return ['bookable' => true, 'reason' => null];
    }

    public function isBookableOn(TourPackage $package, string $date): bool
    {
        return $this->check($package, $date)['bookable'];
    }

    /**
     * @throws ValidationException
     */
    public function validateBookingDate(TourPackage $package, string $date): void
    {
        $result = $this->check($package, $date);

        if (! $result['bookable']) {
            throw ValidationException::withMessages(['travel_date' => $result['reason'] ?? 'This travel date is not available.']);
        }
    }

    protected function parseDate(string $date): ?CarbonInterface
    {
        try {
            $parsed = Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $parsed;
    }
}
