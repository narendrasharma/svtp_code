<?php

namespace App\Services;

use App\Enums\TaxiBookingStatus;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Phase 12A.3: manual taxi dispatch board queries, metrics and
 * internal operational notes.
 *
 * Buckets map directly onto TaxiBookingStatus; time views are
 * Today / Upcoming / Overdue-attention. No GPS, no auto-dispatch.
 */
class TaxiDispatchService
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function buckets(): array
    {
        return [
            'unassigned' => [TaxiBookingStatus::Confirmed->value],
            'assigned' => [TaxiBookingStatus::DriverAssigned->value],
            'en_route' => [TaxiBookingStatus::EnRoute->value],
            'arrived' => [TaxiBookingStatus::Arrived->value],
            'on_board' => [TaxiBookingStatus::PassengerOnBoard->value],
            'completed' => [TaxiBookingStatus::Completed->value],
            'cancelled' => [TaxiBookingStatus::Cancelled->value],
            'no_show' => [TaxiBookingStatus::NoShow->value],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function bucketOptions(): array
    {
        return [
            ['value' => 'unassigned', 'label' => 'Unassigned'],
            ['value' => 'assigned', 'label' => 'Assigned'],
            ['value' => 'en_route', 'label' => 'En Route'],
            ['value' => 'arrived', 'label' => 'Arrived'],
            ['value' => 'on_board', 'label' => 'Passenger On Board'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
            ['value' => 'no_show', 'label' => 'No Show'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function timeViewOptions(): array
    {
        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'upcoming', 'label' => 'Upcoming'],
            ['value' => 'overdue', 'label' => 'Overdue / Attention'],
            ['value' => 'all', 'label' => 'All dates'],
        ];
    }

    /**
     * Dispatch board query with server-side filters.
     *
     * Supported filters: search, bucket, status, time_view, vendor_id,
     * driver_id, vehicle_id, date_from, date_to.
     */
    public function boardQuery(array $filters = [], ?int $vendorScopeId = null): Builder
    {
        $query = TaxiBooking::with([
            'vendorProfile:id,business_name',
            'vehicleType:id,name',
            'assignedDriver:id,first_name,last_name,phone',
            'assignedVehicle:id,name,registration_number',
        ]);

        if ($vendorScopeId !== null) {
            $query->where('vendor_profile_id', $vendorScopeId);
        } elseif (! empty($filters['vendor_id'])) {
            $query->where('vendor_profile_id', (int) $filters['vendor_id']);
        }

        $bucket = (string) ($filters['bucket'] ?? '');
        $buckets = self::buckets();

        if ($bucket !== '' && isset($buckets[$bucket])) {
            $query->whereIn('status', $buckets[$bucket]);
        } elseif (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $timeView = (string) ($filters['time_view'] ?? '');

        if ($timeView === 'today') {
            $query->whereDate('pickup_at', Carbon::today());
        } elseif ($timeView === 'upcoming') {
            $query->whereDate('pickup_at', '>', Carbon::today())
                ->whereNotIn('status', [
                    TaxiBookingStatus::Completed->value,
                    TaxiBookingStatus::Cancelled->value,
                    TaxiBookingStatus::NoShow->value,
                ]);
        } elseif ($timeView === 'overdue') {
            $query->where('pickup_at', '<', now())
                ->whereIn('status', [
                    TaxiBookingStatus::Confirmed->value,
                    TaxiBookingStatus::DriverAssigned->value,
                    TaxiBookingStatus::EnRoute->value,
                    TaxiBookingStatus::Arrived->value,
                    TaxiBookingStatus::PassengerOnBoard->value,
                ]);
        }

        if (! empty($filters['driver_id'])) {
            $query->where('assigned_driver_id', (int) $filters['driver_id']);
        }

        if (! empty($filters['vehicle_id'])) {
            $query->where('assigned_vehicle_id', (int) $filters['vehicle_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('pickup_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('pickup_at', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $term)
                ->orWhere('customer_name', 'like', $term)
                ->orWhere('customer_phone', 'like', $term)
                ->orWhere('pickup_address', 'like', $term)
                ->orWhere('drop_address', 'like', $term));
        }

        return $query->orderBy('pickup_at');
    }

    /**
     * Lightweight dispatch metrics. Vendor scope isolates own data.
     *
     * @return array{unassigned_today: int, assigned_today: int, active_trips: int, overdue_pickups: int, completed_today: int, no_shows_today: int}
     */
    public function metrics(?int $vendorScopeId = null): array
    {
        $today = Carbon::today();

        $base = TaxiBooking::query();

        if ($vendorScopeId !== null) {
            $base->where('vendor_profile_id', $vendorScopeId);
        }

        return [
            'unassigned_today' => (clone $base)->where('status', TaxiBookingStatus::Confirmed->value)->whereDate('pickup_at', $today)->count(),
            'assigned_today' => (clone $base)->where('status', TaxiBookingStatus::DriverAssigned->value)->whereDate('pickup_at', $today)->count(),
            'active_trips' => (clone $base)->whereIn('status', [
                TaxiBookingStatus::DriverAssigned->value,
                TaxiBookingStatus::EnRoute->value,
                TaxiBookingStatus::Arrived->value,
                TaxiBookingStatus::PassengerOnBoard->value,
            ])->count(),
            'overdue_pickups' => (clone $base)->where('pickup_at', '<', now())->whereIn('status', [
                TaxiBookingStatus::Confirmed->value,
                TaxiBookingStatus::DriverAssigned->value,
                TaxiBookingStatus::EnRoute->value,
                TaxiBookingStatus::Arrived->value,
                TaxiBookingStatus::PassengerOnBoard->value,
            ])->count(),
            'completed_today' => (clone $base)->where('status', TaxiBookingStatus::Completed->value)->whereDate('completed_at', $today)->count(),
            'no_shows_today' => (clone $base)->where('status', TaxiBookingStatus::NoShow->value)->whereDate('cancelled_at', $today)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function bucketCounts(?int $vendorScopeId = null): array
    {
        $base = TaxiBooking::query();

        if ($vendorScopeId !== null) {
            $base->where('vendor_profile_id', $vendorScopeId);
        }

        $counts = [];

        foreach (self::buckets() as $bucket => $statuses) {
            $counts[$bucket] = (clone $base)->whereIn('status', $statuses)->count();
        }

        return $counts;
    }

    public function addNote(TaxiBooking $booking, ?User $author, string $body): TaxiBookingNote
    {
        $body = trim($body);

        return $booking->notes()->create([
            'author_id' => $author?->id,
            'body' => mb_substr($body, 0, 2000),
        ]);
    }
}
