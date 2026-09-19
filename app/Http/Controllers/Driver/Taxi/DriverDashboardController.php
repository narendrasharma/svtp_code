<?php

namespace App\Http\Controllers\Driver\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Services\TaxiAvailabilityService;
use App\Services\TaxiDriverEarningService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver Portal dashboard (Phase 12A.4).
 *
 * Operational-only view: today's trips, next pickup, the active trip,
 * upcoming work, today's completions, attention items and the driver's
 * own availability/leave. No financial data, no fleet management.
 */
class DriverDashboardController extends DriverPortalController
{
    public function index(Request $request, TaxiDriverEarningService $earnings): Response
    {
        $driver = $this->driver($request);
        $today = today();

        $openTrips = fn () => $this->openTripsFor($driver);

        $todayTrips = $openTrips()->whereDate('pickup_at', $today)
            ->orderBy('pickup_at')->get();

        $nextPickup = $openTrips()->where('pickup_at', '>=', now())
            ->where('status', TaxiBookingStatus::DriverAssigned->value)
            ->orderBy('pickup_at')->first();

        $activeTrip = $openTrips()->whereIn('status', TaxiAvailabilityService::activeTripStatuses())
            ->where('status', '!=', TaxiBookingStatus::DriverAssigned->value)
            ->orderBy('pickup_at')->first();

        $upcomingTrips = $openTrips()->where('status', TaxiBookingStatus::DriverAssigned->value)
            ->orderBy('pickup_at')->limit(5)->get();

        $completedToday = TaxiBooking::whereHas('assignments', fn ($q) => $q->where('driver_id', $driver->id))
            ->where('status', TaxiBookingStatus::Completed->value)
            ->whereDate('completed_at', $today)->count();

        $attentionCount = $openTrips()->where('pickup_at', '<', now())
            ->whereIn('status', [
                TaxiBookingStatus::DriverAssigned->value,
                TaxiBookingStatus::EnRoute->value,
                TaxiBookingStatus::Arrived->value,
            ])->count();

        $user = $request->user();

        return Inertia::render('Driver/Taxi/Dashboard', [
            'driver' => $driver->load('vendorProfile:id,business_name'),
            'metrics' => [
                'trips_today' => $todayTrips->count(),
                'upcoming' => $openTrips()->where('status', TaxiBookingStatus::DriverAssigned->value)->count(),
                'active' => $openTrips()->whereIn('status', [
                    TaxiBookingStatus::EnRoute->value,
                    TaxiBookingStatus::Arrived->value,
                    TaxiBookingStatus::PassengerOnBoard->value,
                ])->count(),
                'completed_today' => $completedToday,
                'attention' => $attentionCount,
            ],
            'nextPickup' => $nextPickup?->makeHidden(DriverPortalController::hiddenFinancialAttributes()),
            'activeTrip' => $activeTrip?->makeHidden(DriverPortalController::hiddenFinancialAttributes()),
            'todayTrips' => $todayTrips->each->makeHidden(DriverPortalController::hiddenFinancialAttributes()),
            'upcomingTrips' => $upcomingTrips->each->makeHidden(DriverPortalController::hiddenFinancialAttributes()),
            'leave' => $driver->availabilities()
                ->whereIn('status', ['unavailable', 'on_leave'])
                ->where(fn ($q) => $q->whereNull('to_at')->orWhere('to_at', '>=', now()))
                ->orderBy('from_at')->limit(5)->get(),
            'notifications' => $user->notifications()->latest()->limit(5)->get(),
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'earningsSummary' => $earnings->summaryForDriver($driver),
        ]);
    }

    /**
     * Bookings with an OPEN assignment row for this driver.
     */
    protected function openTripsFor(Driver $driver)
    {
        return TaxiBooking::with([
            'assignedVehicle:id,name,registration_number',
            'vehicleType:id,name',
        ])->whereHas('assignments', fn ($q) => $q->open()->where('driver_id', $driver->id));
    }
}
