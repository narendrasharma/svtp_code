<?php

namespace App\Services;

use App\Enums\HotelBookingStatus;
use App\Models\HotelBooking;
use App\Models\HotelReservationNight;
use App\Models\HotelRoomType;
use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HotelOperationsService
{
    public function __construct(protected HotelAvailabilityService $availability) {}

    /** @param Collection<int, Property> $properties */
    public function dashboard(Collection $properties, string $date): array
    {
        $propertyIds = $properties->pluck('id');
        $activeStatuses = [HotelBookingStatus::Pending->value, HotelBookingStatus::Confirmed->value, HotelBookingStatus::CheckedIn->value, HotelBookingStatus::CheckedOut->value, HotelBookingStatus::Completed->value];
        $operational = HotelBooking::query()
            ->whereIn('property_id', $propertyIds)
            ->whereIn('status', $activeStatuses)
            ->where(function ($query) use ($date): void {
                $query->whereDate('check_in', $date)->orWhereDate('check_out', $date)->orWhere(function ($stay) use ($date): void {
                    $stay->whereDate('check_in', '<=', $date)->whereDate('check_out', '>', $date);
                });
            })
            ->with(['property:id,name', 'items:id,hotel_booking_id,room_type_name_snapshot,quantity'])
            ->orderBy('check_in')->get();

        $arrivals = $operational->filter(fn (HotelBooking $booking): bool => $booking->check_in?->toDateString() === $date && ! in_array($booking->status->value, [HotelBookingStatus::Cancelled->value, HotelBookingStatus::NoShow->value], true));
        $departures = $operational->filter(fn (HotelBooking $booking): bool => $booking->check_out?->toDateString() === $date && ! in_array($booking->status->value, [HotelBookingStatus::Cancelled->value, HotelBookingStatus::NoShow->value], true));
        $inHouse = $operational->filter(fn (HotelBooking $booking): bool => $booking->status === HotelBookingStatus::CheckedIn && $booking->check_in?->toDateString() <= $date && $booking->check_out?->toDateString() > $date);
        $attention = $operational->filter(function (HotelBooking $booking) use ($date): bool {
            return ($booking->check_in?->toDateString() === $date && in_array($booking->payment_status->value, ['unpaid', 'partially_paid'], true))
                || ($booking->status === HotelBookingStatus::Confirmed && $booking->check_in?->toDateString() < $date)
                || ($booking->status === HotelBookingStatus::CheckedIn && $booking->check_out?->toDateString() === $date)
                || filled($booking->special_requests);
        })->values();

        $upcoming = HotelBooking::query()->whereIn('property_id', $propertyIds)->whereDate('check_in', '>', $date)->whereDate('check_in', '<=', Carbon::parse($date)->addDays(14))->whereNotIn('status', [HotelBookingStatus::Cancelled->value, HotelBookingStatus::NoShow->value])->with('property:id,name')->orderBy('check_in')->limit(50)->get();
        $rooms = $this->roomSummary($properties, $date);
        $capacity = $rooms->sum('capacity');
        $reserved = $rooms->sum('reserved');

        return [
            'date' => $date,
            'arrivals' => $this->bookingRows($arrivals),
            'departures' => $this->bookingRows($departures),
            'in_house' => $this->bookingRows($inHouse),
            'attention' => $this->bookingRows($attention),
            'upcoming' => $this->bookingRows($upcoming),
            'rooms' => $rooms->values()->all(),
            'kpis' => ['arrivals' => $arrivals->count(), 'departures' => $departures->count(), 'in_house' => $inHouse->count(), 'upcoming' => $upcoming->count(), 'capacity' => $capacity, 'reserved' => $reserved, 'available' => max(0, $rooms->sum('available')), 'occupancy' => $capacity > 0 ? round($reserved / $capacity * 100, 1) : 0, 'attention' => $attention->count()],
        ];
    }

    public function propertyToday(Property $property): string
    {
        return $this->availability->propertyToday($property)->toDateString();
    }

    protected function roomSummary(Collection $properties, string $date): Collection
    {
        $roomTypes = HotelRoomType::query()->whereIn('property_id', $properties->pluck('id'))->active()->with(['property:id,name', 'units:id,room_type_id,status', 'inventories' => fn ($query) => $query->whereDate('inventory_date', $date)])->withSum(['reservationNights as reserved_today' => fn ($query) => $query->whereDate('hotel_reservation_nights.stay_date', $date)->whereIn('hotel_reservation_nights.status', HotelReservationNight::consumingStatuses())], 'quantity')->get();

        return $roomTypes->map(function (HotelRoomType $room): array {
            $capacity = $room->inventory_mode === HotelRoomType::INVENTORY_UNITS ? $room->units->where('status', 'active')->count() : (int) $room->total_units;
            $inventory = $room->inventories->first();
            $effective = $inventory?->capacity_override !== null ? min((int) $inventory->capacity_override, $capacity) : $capacity;
            $blocked = min((int) ($inventory?->blocked_units ?? 0), $effective);
            $available = $inventory?->stop_sell ? 0 : max(0, $effective - $blocked - (int) ($room->reserved_today ?? 0));

            return ['property' => $room->property?->name, 'room_type' => $room->name, 'capacity' => $effective, 'reserved' => (int) ($room->reserved_today ?? 0), 'available' => $available, 'stop_sell' => (bool) ($inventory?->stop_sell ?? false)];
        });
    }

    protected function bookingRows(Collection $bookings): array
    {
        return $bookings->map(function (HotelBooking $booking): array {
            $statusActions = match ($booking->status->value) {
                'confirmed' => ['checked_in' => 'Check in', 'no_show' => 'No show'],
                'checked_in' => ['checked_out' => 'Check out'],
                'checked_out' => ['completed' => 'Complete'],
                default => [],
            };
            $statusUrl = request()->routeIs('vendor.*') ? route('vendor.hotel.bookings.status', $booking) : route('admin.hotel.bookings.status', $booking);

            return ['id' => $booking->id, 'booking_number' => $booking->booking_number, 'guest_name' => $booking->guest_name, 'guest_email' => $booking->guest_email, 'guest_phone' => $booking->guest_phone, 'property' => $booking->property?->name ?? $booking->property_name_snapshot, 'room_types' => $booking->items->map(fn ($item): array => ['name' => $item->room_type_name_snapshot, 'quantity' => $item->quantity])->all(), 'rooms' => $booking->rooms_count, 'adults' => $booking->adults, 'children' => $booking->children, 'check_in' => $booking->check_in?->toDateString(), 'check_out' => $booking->check_out?->toDateString(), 'status' => $booking->status->value, 'payment_status' => $booking->payment_status->value, 'special_requests' => $booking->special_requests, 'url' => request()->routeIs('vendor.*') ? route('vendor.hotel.bookings.show', $booking) : route('admin.hotel.bookings.show', $booking), 'status_url' => $statusUrl, 'status_actions' => $statusActions];
        })->all();
    }
}
