<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\TaxiBooking;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taxi operational dashboard (12A.1): today's rides, upcoming,
 * unassigned, active trips, fleet availability. No advanced analytics.
 */
class TaxiDashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();

        $base = TaxiBooking::query();

        return Inertia::render('Admin/Taxi/Dashboard', [
            'cards' => [
                'today' => (clone $base)->whereDate('pickup_at', $today)->count(),
                'upcoming' => (clone $base)->whereDate('pickup_at', '>', $today)->whereNotIn('status', ['completed', 'cancelled', 'no_show'])->count(),
                'unassigned' => (clone $base)->where('status', 'confirmed')->count(),
                'active_trips' => (clone $base)->whereIn('status', ['driver_assigned', 'en_route', 'arrived', 'passenger_on_board'])->count(),
                'completed_today' => (clone $base)->where('status', 'completed')->whereDate('completed_at', $today)->count(),
                'drivers_available' => Driver::where('is_active', true)->where('availability_status', 'available')->count(),
                'drivers_total' => Driver::where('is_active', true)->count(),
                'vehicles_available' => Vehicle::where('is_active', true)->where('status', 'available')->count(),
                'vehicles_total' => Vehicle::where('is_active', true)->count(),
            ],
            'todayRides' => TaxiBooking::with(['vendorProfile:id,business_name', 'assignedDriver:id,first_name,last_name'])
                ->whereDate('pickup_at', $today)
                ->orderBy('pickup_at')
                ->limit(15)
                ->get(['id', 'reference', 'customer_name', 'pickup_at', 'pickup_address', 'drop_address', 'status', 'vendor_profile_id', 'assigned_driver_id']),
            'unassignedRides' => TaxiBooking::where('status', 'confirmed')
                ->orderBy('pickup_at')
                ->limit(15)
                ->get(['id', 'reference', 'customer_name', 'pickup_at', 'pickup_address', 'drop_address', 'vendor_profile_id']),
            'expiringDriverDocs' => DriverDocument::expiringSoon()->count(),
            'expiringVehicleDocs' => VehicleDocument::expiringSoon()->count(),
        ]);
    }
}
