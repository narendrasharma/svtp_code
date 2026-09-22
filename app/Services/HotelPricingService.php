<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\HotelChargeRule;
use App\Models\HotelDailyRate;
use App\Models\HotelRatePlan;
use App\Models\HotelRateSeason;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\User;
use App\Support\HotelSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hotel rate quote engine (12B.4).
 *
 * Nightly resolution precedence (deterministic, non-stacking):
 *   1. explicit daily amount override
 *   2. single winning season (priority DESC, id ASC)
 *   3. plan base rate
 *
 * Money is decimal strings at scale 2 (bc math, never float). Stay
 * semantics mirror 12B.3: check-in inclusive, check-out exclusive.
 * Inventory answers "can it be sold" (HotelAvailabilityService);
 * this service answers "what does it cost". Neither mutates the other.
 */
class HotelPricingService
{
    public function __construct(protected HotelAvailabilityService $availability) {}

    public function maxBulkDays(): int
    {
        return min(730, max(1, (int) (HotelSettings::get('hotel.pricing.max_bulk_days') ?? 365)));
    }

    /**
     * Property commercial currency. Plans must match it — one currency
     * per stay, no FX conversion in this phase.
     */
    public function effectiveCurrency(Property $property): string
    {
        $raw = strtoupper((string) ($property->currency ?? ''));

        if (preg_match('/^[A-Z]{3}$/', $raw) === 1) {
            return $raw;
        }

        return HotelSettings::defaultCurrency();
    }

    /**
     * Full server-authoritative stay quote for one rate plan.
     *
     * @return array{property_id: int, room_type_id: int, rate_plan_id: int, currency: string, check_in: string, check_out: string, nights_count: int, rooms: int, adults: int, children: int, available: bool, unavailable_reason: ?string, min_available_rooms: ?int, nightly: array<int, array{date: string, base_rate: string, extra_adult: string, extra_child: string, night_total: string}>, subtotal: string, taxes: array<int, array{name: string, amount: string}>, fees: array<int, array{name: string, amount: string}>, included_charges: array<int, array{name: string, amount: string}>, total: string, restrictions: array{min_stay: ?int, max_stay: ?int}, calculated_at: string, snapshot_version: int}
     */
    public function quote(
        HotelRatePlan $plan,
        string $checkIn,
        string $checkOut,
        int $rooms = 1,
        int $adults = 2,
        int $children = 0,
        bool $checkAvailability = true,
    ): array {
        $plan = $plan->fresh();
        $roomType = $plan->roomType()->firstOrFail()->fresh();
        $property = $plan->property()->firstOrFail()->fresh();

        $this->assertPlanUsable($plan, $roomType, $property);

        $nights = $this->availability->nights($checkIn, $checkOut);
        $this->assertOccupancy($roomType, $rooms, $adults, $children);

        $preload = $this->preload($plan, $nights, $checkOut);

        $extraAdults = max(0, $adults - (int) $plan->base_adults * $rooms);
        $extraChildren = max(0, $children - (int) $plan->base_children * $rooms);

        $nightly = [];
        $subtotal = '0.00';
        $minStay = $plan->minimum_stay;
        $maxStay = $plan->maximum_stay;

        foreach ($nights as $date) {
            $row = $preload['rows']->get($date);
            $night = $this->priceNight($plan, $date, $row, $preload['seasons'], $rooms, $extraAdults, $extraChildren);
            $nightly[] = $night;
            $subtotal = bcadd($subtotal, $night['night_total'], 2);

            if ($row?->minimum_stay_override !== null) {
                $minStay = max($minStay ?? 0, (int) $row->minimum_stay_override);
            }

            if ($row?->maximum_stay_override !== null) {
                $maxStay = $maxStay === null ? (int) $row->maximum_stay_override : min($maxStay, (int) $row->maximum_stay_override);
            }
        }

        $stayNights = count($nights);
        $unavailableReason = $this->restrictionReason($plan, $preload, $nights, $checkIn, $checkOut, $stayNights, $minStay, $maxStay);

        $minAvailable = null;

        if ($checkAvailability) {
            $check = $this->availability->checkRoomType($roomType, $checkIn, $checkOut, $rooms);
            $minAvailable = $check['min_available_rooms'];

            if (! $check['available']) {
                $unavailableReason ??= 'Not enough rooms available for the selected dates.';
            }
        }

        $charges = $this->applyCharges($property, $plan, $subtotal, $stayNights, $rooms, $preload['charges']);
        $total = bcadd(bcadd($subtotal, $charges['taxes_total'], 2), $charges['fees_total'], 2);

        return [
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'rate_plan_id' => $plan->id,
            'currency' => $plan->currency,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nights_count' => $stayNights,
            'rooms' => $rooms,
            'adults' => $adults,
            'children' => $children,
            'available' => $unavailableReason === null,
            'unavailable_reason' => $unavailableReason,
            'min_available_rooms' => $minAvailable,
            'nightly' => $nightly,
            'subtotal' => $subtotal,
            'taxes' => $charges['taxes'],
            'fees' => $charges['fees'],
            'included_charges' => $charges['included'],
            'total' => $total,
            'restrictions' => ['min_stay' => $minStay, 'max_stay' => $maxStay],
            'calculated_at' => now()->toIso8601String(),
            'snapshot_version' => 1,
        ];
    }

    /**
     * Quote every active plan of a room type (public rates foundation).
     * Bounded preload: one daily-row query + one season query per call,
     * never one query per night.
     *
     * @return array<int, array<string, mixed>>
     */
    public function quotesForRoom(
        HotelRoomType $roomType,
        string $checkIn,
        string $checkOut,
        int $rooms = 1,
        int $adults = 2,
        int $children = 0,
        bool $checkAvailability = true,
    ): array {
        $roomType = $roomType->fresh();
        $plans = HotelRatePlan::where('hotel_room_type_id', $roomType->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $out = [];

        foreach ($plans as $plan) {
            // Plans outside their effective dates are not public at all;
            // other restriction failures stay visible as unavailable.
            $lastNight = Carbon::parse($checkIn)->toDateString() === $checkOut
                ? $checkIn
                : Carbon::parse($checkOut)->subDay()->toDateString();

            if (! $plan->coversDates($checkIn, $lastNight)) {
                continue;
            }

            try {
                $out[] = $this->quote($plan, $checkIn, $checkOut, $rooms, $adults, $children, $checkAvailability);
            } catch (ValidationException) {
                // Guest/plan mismatch for this plan only — other plans
                // still quote. Restriction failures stay visible inside
                // the quote payload instead.
                continue;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $nights
     * @return array{rows: Collection<string, HotelDailyRate>, seasons: Collection<int, HotelRateSeason>, charges: Collection<int, HotelChargeRule>}
     */
    protected function preload(HotelRatePlan $plan, array $nights, string $checkOut): array
    {
        $dates = [...$nights, $checkOut];

        $rows = HotelDailyRate::where('hotel_rate_plan_id', $plan->id)
            ->whereIn('rate_date', $dates)
            ->get()
            ->keyBy(fn (HotelDailyRate $row): string => $row->rate_date->toDateString());

        $seasons = HotelRateSeason::where('hotel_rate_plan_id', $plan->id)
            ->active()
            ->where('start_date', '<=', max($nights))
            ->where('end_date', '>=', min($nights))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $charges = HotelChargeRule::where('property_id', $plan->property_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ['rows' => $rows, 'seasons' => $seasons, 'charges' => $charges];
    }

    /**
     * @return array{date: string, base_rate: string, extra_adult: string, extra_child: string, night_total: string}
     */
    protected function priceNight(
        HotelRatePlan $plan,
        string $date,
        ?HotelDailyRate $row,
        Collection $seasons,
        int $rooms,
        int $extraAdults,
        int $extraChildren,
    ): array {
        $base = $this->money($plan->base_rate);
        $season = $this->winningSeason($date, $seasons);
        $nightly = $season ? $this->applySeason($base, $season) : $base;

        if ($row?->amount_override !== null) {
            $nightly = $this->money($row->amount_override);
        }

        $adultRate = $row?->extra_adult_override !== null ? $this->money($row->extra_adult_override) : $this->money($plan->extra_adult_rate);
        $childRate = $row?->extra_child_override !== null ? $this->money($row->extra_child_override) : $this->money($plan->extra_child_rate);

        $extraAdult = bcmul($adultRate, (string) $extraAdults, 2);
        $extraChild = bcmul($childRate, (string) $extraChildren, 2);
        $nightTotal = bcadd(bcadd(bcmul($nightly, (string) $rooms, 2), $extraAdult, 2), $extraChild, 2);

        return [
            'date' => $date,
            'base_rate' => $nightly,
            'extra_adult' => $extraAdult,
            'extra_child' => $extraChild,
            'night_total' => $nightTotal,
        ];
    }

    protected function winningSeason(string $date, Collection $seasons): ?HotelRateSeason
    {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek;

        foreach ($seasons as $season) {
            if ($season->start_date->toDateString() <= $date
                && $season->end_date->toDateString() >= $date
                && in_array($dayOfWeek, $season->weekdays(), true)) {
                return $season;
            }
        }

        return null;
    }

    protected function applySeason(string $base, HotelRateSeason $season): string
    {
        $value = $this->money($season->adjustment_value);

        $result = match ($season->adjustment_type) {
            HotelRateSeason::ADJUST_FIXED_PRICE => $value,
            HotelRateSeason::ADJUST_PERCENTAGE => bcadd($base, bcdiv(bcmul($base, $value, 4), '100', 2), 2),
            default => bcadd($base, $value, 2),
        };

        return bccomp($result, '0', 2) < 0 ? '0.00' : $result;
    }

    /**
     * @param  Collection<int, HotelChargeRule>|null  $rules
     * @return array{taxes: array<int, array{name: string, amount: string}>, fees: array<int, array{name: string, amount: string}>, included: array<int, array{name: string, amount: string}>, taxes_total: string, fees_total: string}
     */
    protected function applyCharges(Property $property, HotelRatePlan $plan, string $subtotal, int $nights, int $rooms, ?Collection $rules = null): array
    {
        $rules ??= HotelChargeRule::where('property_id', $property->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $taxes = [];
        $fees = [];
        $included = [];
        $taxesTotal = '0.00';
        $feesTotal = '0.00';

        foreach ($rules as $rule) {
            if ($rule->currency !== $plan->currency) {
                continue;
            }

            $value = $this->money($rule->value);

            if ($rule->included_in_price) {
                if ($rule->calculation !== HotelChargeRule::CALC_PERCENTAGE) {
                    continue;
                }

                // Extracted for display only — never added to the total.
                $amount = bcdiv(bcmul($subtotal, $value, 4), bcadd('100', $value, 4), 2);
                $included[] = ['name' => $rule->name, 'amount' => $amount];

                continue;
            }

            $amount = match ($rule->calculation) {
                HotelChargeRule::CALC_PERCENTAGE => bcdiv(bcmul($subtotal, $value, 4), '100', 2),
                HotelChargeRule::CALC_FIXED_NIGHT => bcmul($value, (string) $nights, 2),
                HotelChargeRule::CALC_FIXED_ROOM => bcmul($value, (string) $rooms, 2),
                HotelChargeRule::CALC_FIXED_ROOM_NIGHT => bcmul($value, (string) ($nights * $rooms), 2),
                default => $value,
            };

            if ($rule->charge_type === HotelChargeRule::TYPE_TAX) {
                $taxes[] = ['name' => $rule->name, 'amount' => $amount];
                $taxesTotal = bcadd($taxesTotal, $amount, 2);
            } else {
                $fees[] = ['name' => $rule->name, 'amount' => $amount];
                $feesTotal = bcadd($feesTotal, $amount, 2);
            }
        }

        return [
            'taxes' => $taxes, 'fees' => $fees, 'included' => $included,
            'taxes_total' => $taxesTotal, 'fees_total' => $feesTotal,
        ];
    }

    protected function assertPlanUsable(HotelRatePlan $plan, HotelRoomType $roomType, Property $property): void
    {
        if ((int) $plan->hotel_room_type_id !== (int) $roomType->id
            || (int) $plan->property_id !== (int) $property->id
            || (int) $roomType->property_id !== (int) $property->id) {
            throw ValidationException::withMessages(['rate_plan_id' => 'Rate plan does not belong to this room.']);
        }

        if (! $plan->is_active || $roomType->status !== RoomTypeStatus::Active->value) {
            throw ValidationException::withMessages(['rate_plan_id' => 'This rate is not available.']);
        }

        if ($property->status !== PropertyStatus::Published->value) {
            throw ValidationException::withMessages(['property_id' => 'This property is not available.']);
        }

        if ($plan->currency !== $this->effectiveCurrency($property)) {
            throw ValidationException::withMessages(['currency' => 'Rate plan currency must match the property currency.']);
        }
    }

    protected function assertOccupancy(HotelRoomType $roomType, int $rooms, int $adults, int $children): void
    {
        if ($rooms < 1) {
            throw ValidationException::withMessages(['rooms' => 'At least one room is required.']);
        }

        if ($adults < 1 || $adults > (int) $roomType->max_adults * $rooms) {
            throw ValidationException::withMessages(['adults' => 'Adult count exceeds room capacity.']);
        }

        if ($children < 0 || $children > (int) $roomType->max_children * $rooms) {
            throw ValidationException::withMessages(['children' => 'Child count exceeds room capacity.']);
        }

        if ($adults + $children > (int) $roomType->max_occupancy * $rooms) {
            throw ValidationException::withMessages(['adults' => 'Guest count exceeds maximum occupancy.']);
        }
    }

    /**
     * @param  array<int, string>  $nights
     * @param  array{rows: Collection<string, HotelDailyRate>, seasons: Collection<int, HotelRateSeason>, charges: Collection<int, HotelChargeRule>}  $preload
     */
    protected function restrictionReason(
        HotelRatePlan $plan,
        array $preload,
        array $nights,
        string $checkIn,
        string $checkOut,
        int $stayNights,
        ?int $minStay,
        ?int $maxStay,
    ): ?string {
        $lastNight = end($nights);

        if (! $plan->coversDates($checkIn, $lastNight)) {
            return 'This rate is not valid for the selected dates.';
        }

        if ($minStay !== null && $stayNights < $minStay) {
            return "This rate requires a minimum stay of {$minStay} nights.";
        }

        if ($maxStay !== null && $stayNights > $maxStay) {
            return "This rate allows a maximum stay of {$maxStay} nights.";
        }

        foreach ($nights as $date) {
            if ($preload['rows']->get($date)?->stop_sell) {
                return 'This rate is closed for the selected dates.';
            }
        }

        if ($preload['rows']->get($checkIn)?->closed_to_arrival) {
            return 'Arrival is not allowed on the selected check-in date for this rate.';
        }

        if ($preload['rows']->get($checkOut)?->closed_to_departure) {
            return 'Departure is not allowed on the selected check-out date for this rate.';
        }

        return null;
    }

    /**
     * Admin/Vendor calendar payload for an inclusive range. One bounded
     * daily-row query; seasons overlapping the range in one more.
     *
     * @return array<int, array{date: string, base_rate: string, effective_rate: string, amount_override: ?string, stop_sell: bool, closed_to_arrival: bool, closed_to_departure: bool, has_season: bool, note: ?string}>
     */
    public function calendar(HotelRatePlan $plan, string $start, string $end, ?int $maxDays = null): array
    {
        $dates = $this->availability->datesInclusive($start, $end, $maxDays ?? $this->maxBulkDays());

        $rows = HotelDailyRate::where('hotel_rate_plan_id', $plan->id)
            ->whereIn('rate_date', $dates)
            ->get()
            ->keyBy(fn (HotelDailyRate $row): string => $row->rate_date->toDateString());

        $seasons = HotelRateSeason::where('hotel_rate_plan_id', $plan->id)
            ->where('start_date', '<=', max($dates))
            ->where('end_date', '>=', min($dates))
            ->get();

        $base = $this->money($plan->base_rate);
        $out = [];

        foreach ($dates as $date) {
            $row = $rows->get($date);
            $season = $this->winningSeason($date, $seasons);
            $effective = $row?->amount_override !== null
                ? $this->money($row->amount_override)
                : ($season ? $this->applySeason($base, $season) : $base);

            $out[] = [
                'date' => $date,
                'base_rate' => $base,
                'effective_rate' => $effective,
                'amount_override' => $row?->amount_override !== null ? $this->money($row->amount_override) : null,
                'stop_sell' => (bool) ($row?->stop_sell ?? false),
                'closed_to_arrival' => (bool) ($row?->closed_to_arrival ?? false),
                'closed_to_departure' => (bool) ($row?->closed_to_departure ?? false),
                'has_season' => $season !== null,
                'note' => $row?->note,
            ];
        }

        return $out;
    }

    /**
     * @param  array{amount_override?: ?string, extra_adult_override?: ?string, extra_child_override?: ?string, minimum_stay_override?: ?int, maximum_stay_override?: ?int, stop_sell?: bool, closed_to_arrival?: bool, closed_to_departure?: bool, note?: ?string}  $ops
     */
    public function setDay(HotelRatePlan $plan, string $date, array $ops, ?User $actor = null): void
    {
        $normalized = $this->normalizeOps($this->parseDate($date, 'date'), $ops);

        DB::transaction(function () use ($plan, $normalized, $actor): void {
            $this->persistDay($plan, $normalized['date'], $normalized, $actor);
        });

        app(ActivityLogger::class)->log(
            'hotel_pricing.updated', 'hotels',
            "Daily rate updated for {$plan->name} on {$normalized['date']}",
            $plan, null,
            ['date' => $normalized['date']] + $normalized['state'],
            $actor
        );
    }

    /**
     * @param  array{amount_override?: ?string, extra_adult_override?: ?string, extra_child_override?: ?string, minimum_stay_override?: ?int, maximum_stay_override?: ?int, stop_sell?: bool, closed_to_arrival?: bool, closed_to_departure?: bool, note?: ?string}  $ops
     * @param  array<int, mixed>|null  $weekdays  Carbon day numbers (0 = Sunday … 6 = Saturday). Null/empty = every date.
     * @return array{rate_plan_id: int, start_date: string, end_date: string, dates_affected: int, rows_stored: int}
     */
    public function applyRange(HotelRatePlan $plan, string $start, string $end, array $ops, ?User $actor = null, mixed $weekdays = null): array
    {
        $dates = $this->availability->datesInclusive($start, $end, $this->maxBulkDays());
        $filter = $this->normalizeWeekdays($weekdays);
        $dates = $this->filterDatesByWeekdays($dates, $filter);

        $normalized = [];

        foreach ($dates as $date) {
            $normalized[$date] = $this->normalizeOps($date, $ops);
        }

        $stored = DB::transaction(function () use ($plan, $normalized, $actor): int {
            $count = 0;

            foreach ($normalized as $date => $day) {
                if ($this->persistDay($plan, $date, $day, $actor)) {
                    $count++;
                }
            }

            return $count;
        });

        app(ActivityLogger::class)->log(
            'hotel_pricing.range_updated', 'hotels',
            "Daily rates updated for {$plan->name}: {$start} → {$end} ({$stored} rows stored)",
            $plan, null,
            ['start_date' => $start, 'end_date' => $end, 'dates_affected' => count($dates), 'rows_stored' => $stored, 'weekdays' => $filter],
            $actor
        );

        return [
            'rate_plan_id' => $plan->id,
            'start_date' => $start,
            'end_date' => $end,
            'dates_affected' => count($dates),
            'rows_stored' => $stored,
        ];
    }

    public function clearDay(HotelRatePlan $plan, string $date, ?User $actor = null): bool
    {
        $day = $this->parseDate($date, 'date');

        $deleted = (bool) HotelDailyRate::where('hotel_rate_plan_id', $plan->id)
            ->where('rate_date', $day)
            ->delete();

        if ($deleted) {
            app(ActivityLogger::class)->log(
                'hotel_pricing.cleared', 'hotels',
                "Daily rate override cleared for {$plan->name} on {$day}",
                $plan, null, ['date' => $day], $actor
            );
        }

        return $deleted;
    }

    public function clearRange(HotelRatePlan $plan, string $start, string $end, ?User $actor = null, mixed $weekdays = null): int
    {
        $dates = $this->availability->datesInclusive($start, $end, $this->maxBulkDays());
        $filter = $this->normalizeWeekdays($weekdays);
        $dates = $this->filterDatesByWeekdays($dates, $filter);

        if ($dates === []) {
            return 0;
        }

        $deleted = DB::transaction(fn (): int => HotelDailyRate::where('hotel_rate_plan_id', $plan->id)
            ->whereIn('rate_date', $dates)
            ->delete());

        if ($deleted > 0) {
            app(ActivityLogger::class)->log(
                'hotel_pricing.cleared', 'hotels',
                "Daily rate overrides cleared for {$plan->name}: {$start} → {$end} ({$deleted} rows)",
                $plan, null,
                ['start_date' => $start, 'end_date' => $end, 'rows_deleted' => $deleted, 'weekdays' => $filter],
                $actor
            );
        }

        return $deleted;
    }

    /**
     * Normalize a weekday filter to Carbon day numbers (0 = Sunday … 6 = Saturday).
     *
     * Null/empty means "every date" (backward-compatible bulk behavior).
     * Follows the HotelRateSeason::applicable_weekdays convention.
     *
     * @return array<int, int>|null
     */
    public function normalizeWeekdays(mixed $weekdays): ?array
    {
        if ($weekdays === null || $weekdays === '' || $weekdays === []) {
            return null;
        }

        if (! is_array($weekdays)) {
            throw ValidationException::withMessages(['weekdays' => 'Weekdays must be an array.']);
        }

        $days = [];

        foreach ($weekdays as $day) {
            if (is_string($day) && trim($day) !== '' && is_numeric(trim($day))) {
                $day = trim($day);
            }

            if (! is_int($day) && ! (is_string($day) && ctype_digit($day))) {
                throw ValidationException::withMessages(['weekdays' => 'Each weekday must be a day number (0 = Sunday … 6 = Saturday).']);
            }

            $int = (int) $day;

            if ($int < 0 || $int > 6) {
                throw ValidationException::withMessages(['weekdays' => 'Each weekday must be between 0 (Sunday) and 6 (Saturday).']);
            }

            $days[] = $int;
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days === [] ? null : $days;
    }

    /**
     * @param  array<int, string>  $dates
     * @param  array<int, int>|null  $weekdays
     * @return array<int, string>
     */
    protected function filterDatesByWeekdays(array $dates, ?array $weekdays): array
    {
        if ($weekdays === null || $weekdays === []) {
            return $dates;
        }

        $allowed = array_flip($weekdays);

        return array_values(array_filter(
            $dates,
            fn (string $date): bool => isset($allowed[(int) Carbon::parse($date)->dayOfWeek])
        ));
    }

    /**
     * @param  array{amount_override?: ?string, extra_adult_override?: ?string, extra_child_override?: ?string, minimum_stay_override?: ?int, maximum_stay_override?: ?int, stop_sell?: bool, closed_to_arrival?: bool, closed_to_departure?: bool, note?: ?string}  $ops
     * @return array{date: string, state: array{amount_override: ?string, extra_adult_override: ?string, extra_child_override: ?string, minimum_stay_override: ?int, maximum_stay_override: ?int, stop_sell: bool, closed_to_arrival: bool, closed_to_departure: bool, note: ?string}}
     */
    protected function normalizeOps(string $date, array $ops): array
    {
        $moneyOrNull = function (mixed $value, string $field): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            if (! is_numeric($value) || bccomp((string) $value, '0', 2) < 0) {
                throw ValidationException::withMessages([$field => 'Amount must be zero or more.']);
            }

            return $this->money($value);
        };

        $stayOrNull = function (mixed $value, string $field): ?int {
            if ($value === null || $value === '') {
                return null;
            }

            if (! is_numeric($value) || (int) $value < 1 || (int) $value > 365) {
                throw ValidationException::withMessages([$field => 'Stay length must be between 1 and 365 nights.']);
            }

            return (int) $value;
        };

        $note = array_key_exists('note', $ops) && trim((string) $ops['note']) !== ''
            ? mb_substr(trim(strip_tags((string) $ops['note'])), 0, 500)
            : null;

        return [
            'date' => $date,
            'state' => [
                'amount_override' => $moneyOrNull($ops['amount_override'] ?? null, 'amount_override'),
                'extra_adult_override' => $moneyOrNull($ops['extra_adult_override'] ?? null, 'extra_adult_override'),
                'extra_child_override' => $moneyOrNull($ops['extra_child_override'] ?? null, 'extra_child_override'),
                'minimum_stay_override' => $stayOrNull($ops['minimum_stay_override'] ?? null, 'minimum_stay_override'),
                'maximum_stay_override' => $stayOrNull($ops['maximum_stay_override'] ?? null, 'maximum_stay_override'),
                'stop_sell' => (bool) ($ops['stop_sell'] ?? false),
                'closed_to_arrival' => (bool) ($ops['closed_to_arrival'] ?? false),
                'closed_to_departure' => (bool) ($ops['closed_to_departure'] ?? false),
                'note' => $note,
            ],
        ];
    }

    /**
     * @param  array{date: string, state: array{amount_override: ?string, extra_adult_override: ?string, extra_child_override: ?string, minimum_stay_override: ?int, maximum_stay_override: ?int, stop_sell: bool, closed_to_arrival: bool, closed_to_departure: bool, note: ?string}}  $day
     */
    protected function persistDay(HotelRatePlan $plan, string $date, array $day, ?User $actor): bool
    {
        $state = $day['state'];
        $isDefault = $state['amount_override'] === null
            && $state['extra_adult_override'] === null
            && $state['extra_child_override'] === null
            && $state['minimum_stay_override'] === null
            && $state['maximum_stay_override'] === null
            && ! $state['stop_sell']
            && ! $state['closed_to_arrival']
            && ! $state['closed_to_departure']
            && $state['note'] === null;

        if ($isDefault) {
            HotelDailyRate::where('hotel_rate_plan_id', $plan->id)
                ->where('rate_date', $date)
                ->delete();

            return false;
        }

        HotelDailyRate::updateOrCreate(
            ['hotel_rate_plan_id' => $plan->id, 'rate_date' => $date],
            $state + ['source' => HotelDailyRate::SOURCE_MANUAL, 'created_by' => $actor?->id]
        );

        return true;
    }

    protected function parseDate(string $value, string $field): string
    {
        $date = Carbon::createFromFormat('Y-m-d', trim($value));

        if (! $date || $date->format('Y-m-d') !== trim($value)) {
            throw ValidationException::withMessages([$field => 'Enter a valid date (YYYY-MM-DD).']);
        }

        return $date->toDateString();
    }

    protected function money(mixed $value): string
    {
        return bcadd((string) $value, '0', 2);
    }
}
