<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\HotelReservationNight;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\User;
use App\Support\HotelSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hotel inventory & availability foundation (12B.3).
 *
 * SPARSE architecture: dates without an inventory row derive from the
 * room type's structural capacity (total_units, or active units in
 * units mode). Rows exist only for manual overrides, blocked units,
 * stop-sell or internal notes.
 *
 * Stay semantics: check-in inclusive, check-out exclusive. A 2-night
 * Oct 10 → Oct 12 stay consumes Oct 10 and Oct 11 only.
 *
 * Phase 12B.5 seam: reservedRooms() currently returns 0; future
 * booking allocations subtract here without touching callers. No
 * pricing lives in this service (12B.4 owns rates).
 */
class HotelAvailabilityService
{
    public function maxBulkDays(): int
    {
        return min(730, max(1, (int) (HotelSettings::get('hotel.inventory.max_bulk_days') ?? 365)));
    }

    public function maxStayNights(): int
    {
        return min(90, max(1, (int) (HotelSettings::get('hotel.availability.max_stay_nights') ?? 30)));
    }

    public function publicCheckEnabled(): bool
    {
        return HotelSettings::enabled('hotel.availability.public_check_enabled');
    }

    /**
     * Structural sellable capacity: declared total in aggregate mode, or
     * the live count of active physical units in units mode. Unit status
     * changes and total_units edits flow through automatically.
     */
    public function structuralCapacity(HotelRoomType $roomType): int
    {
        return max(0, $roomType->capacityUnits());
    }

    /**
     * Reservation quantities are separate from configured inventory. Only
     * active reservation states consume capacity.
     */
    public function reservedRooms(HotelRoomType $roomType, string $date): int
    {
        return (int) HotelReservationNight::query()
            ->where('room_type_id', $roomType->id)
            ->whereBetween('stay_date', [$date.' 00:00:00', $date.' 23:59:59'])
            ->whereIn('status', HotelReservationNight::consumingStatuses())
            ->sum('quantity');
    }

    /** @return array<string, int> */
    public function reservedRoomsForDates(HotelRoomType $roomType, array $dates, ?int $excludeBookingId = null): array
    {
        if ($dates === []) {
            return [];
        }

        $rows = HotelReservationNight::query()
            ->where('room_type_id', $roomType->id)
            ->whereBetween('stay_date', [$dates[0].' 00:00:00', end($dates).' 23:59:59'])
            ->whereIn('status', HotelReservationNight::consumingStatuses())
            ->when($excludeBookingId !== null, fn ($query) => $query->whereHas('item', fn ($item) => $item->where('hotel_booking_id', '!=', $excludeBookingId)))
            ->selectRaw('stay_date, SUM(quantity) as quantity')
            ->groupBy('stay_date')
            ->get();

        return $rows->mapWithKeys(fn ($row): array => [Carbon::parse($row->stay_date)->toDateString() => (int) $row->quantity])->all();
    }

    /**
     * Stay nights for [checkIn, checkOut): checkout exclusive.
     *
     * @return array<int, string> Y-m-d dates
     */
    public function nights(string $checkIn, string $checkOut, ?int $maxNights = null): array
    {
        $in = $this->parseDate($checkIn, 'check_in');
        $out = $this->parseDate($checkOut, 'check_out');

        if (! $out->gt($in)) {
            throw ValidationException::withMessages(['check_out' => 'Check-out must be after check-in.']);
        }

        $limit = $maxNights ?? $this->maxStayNights();

        if ($in->diffInDays($out) > $limit) {
            throw ValidationException::withMessages(['check_out' => "Stays are limited to {$limit} nights."]);
        }

        $dates = [];

        for ($day = $in->copy(); $day->lt($out); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * Inclusive calendar dates for Admin/Vendor range views and writes.
     *
     * @return array<int, string> Y-m-d dates
     */
    public function datesInclusive(string $start, string $end, ?int $maxDays = null): array
    {
        $from = $this->parseDate($start, 'start_date');
        $to = $this->parseDate($end, 'end_date');

        if ($to->lt($from)) {
            throw ValidationException::withMessages(['end_date' => 'End date must not be before start date.']);
        }

        $limit = $maxDays ?? $this->maxBulkDays();

        if ($from->diffInDays($to) + 1 > $limit) {
            throw ValidationException::withMessages(['end_date' => "Date ranges are limited to {$limit} days."]);
        }

        $dates = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * @return array{date: string, structural_capacity: int, override_capacity: ?int, blocked: int, reserved: int, available: int, stop_sell: bool}
     */
    public function nightly(HotelRoomType $roomType, string $date, ?HotelRoomInventory $row = null, ?int $reserved = null): array
    {
        $row ??= HotelRoomInventory::where('hotel_room_type_id', $roomType->id)
            ->where('inventory_date', $date)
            ->first();

        $structural = $this->structuralCapacity($roomType->fresh());
        $override = $row?->capacity_override;
        $effective = $override !== null ? min((int) $override, $structural) : $structural;
        $blocked = min((int) ($row?->blocked_units ?? 0), $effective);
        $stop = (bool) ($row?->stop_sell ?? false);
        $reserved ??= $this->reservedRooms($roomType, $date);

        return [
            'date' => $date,
            'structural_capacity' => $structural,
            'override_capacity' => $override !== null ? (int) $override : null,
            'blocked' => $blocked,
            'reserved' => $reserved,
            'available' => $stop ? 0 : max(0, $effective - $blocked - $reserved),
            'stop_sell' => $stop,
        ];
    }

    /**
     * Full stay check for one room type. Requested quantity must fit
     * EVERY night — availability is the minimum, never the average.
     *
     * @return array{room_type_id: int, check_in: string, check_out: string, requested_rooms: int, available: bool, min_available_rooms: int, nights: array<int, array{date: string, structural_capacity: int, override_capacity: ?int, blocked: int, reserved: int, available: int, stop_sell: bool}>}
     */
    public function checkRoomType(HotelRoomType $roomType, string $checkIn, string $checkOut, int $rooms = 1, ?int $maxNights = null, ?int $excludeBookingId = null): array
    {
        if ($rooms < 1) {
            throw ValidationException::withMessages(['rooms' => 'At least one room is required.']);
        }

        $dates = $this->nights($checkIn, $checkOut, $maxNights);

        $rows = HotelRoomInventory::where('hotel_room_type_id', $roomType->id)
            ->whereIn('inventory_date', $dates)
            ->get()
            ->keyBy(fn (HotelRoomInventory $row): string => $row->inventory_date->toDateString());

        $nights = [];
        $reservedByDate = $this->reservedRoomsForDates($roomType, $dates, $excludeBookingId);

        foreach ($dates as $date) {
            $nights[] = $this->nightly($roomType, $date, $rows->get($date), $reservedByDate[$date] ?? 0);
        }

        $min = min(array_column($nights, 'available'));

        return [
            'room_type_id' => $roomType->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'requested_rooms' => $rooms,
            'available' => $min >= $rooms,
            'min_available_rooms' => $min,
            'nights' => $nights,
        ];
    }

    /**
     * Eligible room types for a stay: active rooms of a published
     * property, each with its own stay check. One bounded inventory
     * query serves every room — never one query per night.
     *
     * @return array<int, array{room_type_id: int, available: bool, min_available_rooms: int, nights: array<int, mixed>}>
     */
    public function availableRoomTypes(Property $property, string $checkIn, string $checkOut, int $rooms = 1, ?int $maxNights = null): array
    {
        if ($property->status !== PropertyStatus::Published->value) {
            return [];
        }

        if ($rooms < 1) {
            throw ValidationException::withMessages(['rooms' => 'At least one room is required.']);
        }

        $dates = $this->nights($checkIn, $checkOut, $maxNights);

        $roomTypes = HotelRoomType::where('property_id', $property->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($roomTypes->isEmpty()) {
            return [];
        }

        $rows = HotelRoomInventory::whereIn('hotel_room_type_id', $roomTypes->pluck('id')->all())
            ->whereIn('inventory_date', $dates)
            ->get()
            ->groupBy('hotel_room_type_id');

        $out = [];
        $reserved = HotelReservationNight::query()
            ->whereIn('room_type_id', $roomTypes->pluck('id')->all())
            ->whereBetween('stay_date', [$dates[0].' 00:00:00', end($dates).' 23:59:59'])
            ->whereIn('status', HotelReservationNight::consumingStatuses())
            ->selectRaw('room_type_id, stay_date, SUM(quantity) as quantity')
            ->groupBy('room_type_id', 'stay_date')
            ->get()
            ->groupBy('room_type_id')
            ->map(fn ($items) => $items->mapWithKeys(fn ($row): array => [Carbon::parse($row->stay_date)->toDateString() => (int) $row->quantity])->all());

        foreach ($roomTypes as $roomType) {
            $byDate = ($rows->get($roomType->id) ?? collect())
                ->keyBy(fn (HotelRoomInventory $row): string => $row->inventory_date->toDateString());

            $nights = [];

            foreach ($dates as $date) {
                $nights[] = $this->nightly($roomType, $date, $byDate->get($date), $reserved->get($roomType->id, [])[$date] ?? 0);
            }

            $min = min(array_column($nights, 'available'));

            $out[] = [
                'room_type_id' => $roomType->id,
                'available' => $min >= $rooms,
                'min_available_rooms' => $min,
                'nights' => $nights,
            ];
        }

        return $out;
    }

    /**
     * Admin/Vendor calendar payload for an inclusive range. One bounded
     * query; notes included (staff-only callers).
     *
     * @return array<int, array{date: string, structural_capacity: int, capacity_override: ?int, blocked_units: int, stop_sell: bool, note: ?string, effective_available: int}>
     */
    public function calendar(HotelRoomType $roomType, string $start, string $end, ?int $maxDays = null): array
    {
        $dates = $this->datesInclusive($start, $end, $maxDays);

        $rows = HotelRoomInventory::where('hotel_room_type_id', $roomType->id)
            ->whereIn('inventory_date', $dates)
            ->get()
            ->keyBy(fn (HotelRoomInventory $row): string => $row->inventory_date->toDateString());

        $out = [];

        foreach ($dates as $date) {
            $row = $rows->get($date);
            $night = $this->nightly($roomType, $date, $row);

            $out[] = [
                'date' => $date,
                'structural_capacity' => $night['structural_capacity'],
                'capacity_override' => $night['override_capacity'],
                'blocked_units' => $row ? (int) $row->blocked_units : 0,
                'stop_sell' => $night['stop_sell'],
                'note' => $row?->note,
                'effective_available' => $night['available'],
            ];
        }

        return $out;
    }

    /**
     * Set one date's inventory. Default-equivalent input deletes the row
     * to keep storage sparse.
     *
     * @param  array{capacity_override?: ?int, blocked_units?: int, stop_sell?: bool, note?: ?string}  $ops
     * @return array{date: string, structural_capacity: int, override_capacity: ?int, blocked: int, reserved: int, available: int, stop_sell: bool}
     */
    public function setDay(HotelRoomType $roomType, string $date, array $ops, ?User $actor = null): array
    {
        $normalized = $this->normalizeOps($roomType, $this->parseDate($date, 'date')->toDateString(), $ops);

        DB::transaction(function () use ($roomType, $normalized, $actor): void {
            $this->persistDay($roomType, $normalized['date'], $normalized, $actor);
        });

        app(ActivityLogger::class)->log(
            'hotel_inventory.updated', 'hotels',
            "Inventory updated for {$roomType->name} on {$normalized['date']}",
            $roomType, null,
            ['date' => $normalized['date']] + $normalized['state'],
            $actor
        );

        return $this->nightly($roomType, $normalized['date']);
    }

    /**
     * Bulk range update: every date validated BEFORE anything writes, so
     * a mixed invalid payload fails atomically with zero partial rows.
     *
     * @param  array{capacity_override?: ?int, blocked_units?: int, stop_sell?: bool, note?: ?string}  $ops
     * @return array{room_type_id: int, start_date: string, end_date: string, dates_affected: int, rows_stored: int}
     */
    public function applyRange(HotelRoomType $roomType, string $start, string $end, array $ops, ?User $actor = null): array
    {
        $dates = $this->datesInclusive($start, $end);

        $normalized = [];

        foreach ($dates as $date) {
            $normalized[$date] = $this->normalizeOps($roomType, $date, $ops);
        }

        $stored = DB::transaction(function () use ($roomType, $normalized, $actor): int {
            $count = 0;

            foreach ($normalized as $date => $day) {
                if ($this->persistDay($roomType, $date, $day, $actor)) {
                    $count++;
                }
            }

            return $count;
        });

        app(ActivityLogger::class)->log(
            'hotel_inventory.range_updated', 'hotels',
            "Inventory range updated for {$roomType->name}: {$start} → {$end} ({$stored} rows stored)",
            $roomType, null,
            ['start_date' => $start, 'end_date' => $end, 'dates_affected' => count($dates), 'rows_stored' => $stored] + $ops,
            $actor
        );

        return [
            'room_type_id' => $roomType->id,
            'start_date' => $start,
            'end_date' => $end,
            'dates_affected' => count($dates),
            'rows_stored' => $stored,
        ];
    }

    public function clearDay(HotelRoomType $roomType, string $date, ?User $actor = null): bool
    {
        $day = $this->parseDate($date, 'date')->toDateString();

        $deleted = (bool) HotelRoomInventory::where('hotel_room_type_id', $roomType->id)
            ->where('inventory_date', $day)
            ->delete();

        if ($deleted) {
            app(ActivityLogger::class)->log(
                'hotel_inventory.cleared', 'hotels',
                "Inventory override cleared for {$roomType->name} on {$day}",
                $roomType, null, ['date' => $day], $actor
            );
        }

        return $deleted;
    }

    public function clearRange(HotelRoomType $roomType, string $start, string $end, ?User $actor = null): int
    {
        $dates = $this->datesInclusive($start, $end);

        $deleted = DB::transaction(fn (): int => HotelRoomInventory::where('hotel_room_type_id', $roomType->id)
            ->whereIn('inventory_date', $dates)
            ->delete());

        if ($deleted > 0) {
            app(ActivityLogger::class)->log(
                'hotel_inventory.cleared', 'hotels',
                "Inventory overrides cleared for {$roomType->name}: {$start} → {$end} ({$deleted} rows)",
                $roomType, null,
                ['start_date' => $start, 'end_date' => $end, 'rows_deleted' => $deleted],
                $actor
            );
        }

        return $deleted;
    }

    /**
     * @param  array{capacity_override?: ?int, blocked_units?: int, stop_sell?: bool, note?: ?string}  $ops
     * @return array{date: string, state: array{capacity_override: ?int, blocked_units: int, stop_sell: bool, note: ?string}}
     */
    protected function normalizeOps(HotelRoomType $roomType, string $date, array $ops): array
    {
        $structural = $this->structuralCapacity($roomType->fresh());

        $override = array_key_exists('capacity_override', $ops) ? $ops['capacity_override'] : null;
        $blocked = array_key_exists('blocked_units', $ops) ? (int) $ops['blocked_units'] : 0;
        $stop = array_key_exists('stop_sell', $ops) ? (bool) $ops['stop_sell'] : false;
        $note = array_key_exists('note', $ops) && trim((string) $ops['note']) !== ''
            ? mb_substr(trim(strip_tags((string) $ops['note'])), 0, 500)
            : null;

        if ($override !== null) {
            $override = (int) $override;

            if ($override < 0 || $override > $structural) {
                throw ValidationException::withMessages([
                    'capacity_override' => "Override must be between 0 and the structural capacity ({$structural}).",
                ]);
            }
        }

        $effective = $override ?? $structural;

        if ($blocked < 0 || $blocked > $effective) {
            throw ValidationException::withMessages([
                'blocked_units' => "Blocked units must be between 0 and the effective capacity ({$effective}).",
            ]);
        }

        return [
            'date' => $date,
            'state' => [
                'capacity_override' => $override,
                'blocked_units' => $blocked,
                'stop_sell' => $stop,
                'note' => $note,
            ],
        ];
    }

    /**
     * Upsert-or-delete one normalized day. Returns true when a row
     * remains stored, false when the day is at default (row removed).
     *
     * @param  array{date: string, state: array{capacity_override: ?int, blocked_units: int, stop_sell: bool, note: ?string}}  $day
     */
    protected function persistDay(HotelRoomType $roomType, string $date, array $day, ?User $actor): bool
    {
        $state = $day['state'];
        $isDefault = $state['capacity_override'] === null
            && $state['blocked_units'] === 0
            && ! $state['stop_sell']
            && $state['note'] === null;

        if ($isDefault) {
            HotelRoomInventory::where('hotel_room_type_id', $roomType->id)
                ->where('inventory_date', $date)
                ->delete();

            return false;
        }

        HotelRoomInventory::updateOrCreate(
            ['hotel_room_type_id' => $roomType->id, 'inventory_date' => $date],
            $state + ['source' => HotelRoomInventory::SOURCE_MANUAL, 'created_by' => $actor?->id]
        );

        return true;
    }

    protected function parseDate(string $value, string $field): Carbon
    {
        $date = Carbon::createFromFormat('Y-m-d', trim($value));

        if (! $date || $date->format('Y-m-d') !== trim($value)) {
            throw ValidationException::withMessages([$field => 'Enter a valid date (YYYY-MM-DD).']);
        }

        return $date->startOfDay();
    }

    public function propertyToday(Property $property): Carbon
    {
        $timezone = (string) ($property->timezone ?? '');

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = config('app.timezone', 'UTC');
        }

        return Carbon::today($timezone);
    }
}
