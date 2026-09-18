<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TaxiDashboardController extends Controller
{
    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);
        $today = Carbon::today();

        $eligibleStatuses = [
            TaxiBookingStatus::Confirmed->value,
            TaxiBookingStatus::DriverAssigned->value,
            TaxiBookingStatus::EnRoute->value,
            TaxiBookingStatus::Arrived->value,
            TaxiBookingStatus::PassengerOnBoard->value,
        ];

        $activeStatuses = [
            TaxiBookingStatus::DriverAssigned->value,
            TaxiBookingStatus::EnRoute->value,
            TaxiBookingStatus::Arrived->value,
            TaxiBookingStatus::PassengerOnBoard->value,
        ];

        $base = TaxiBooking::where('vendor_profile_id', $profile->id);

        $cards = [
            'today' => (clone $base)->whereDate('pickup_at', $today)->count(),
            'upcoming' => (clone $base)->whereDate('pickup_at', '>', $today)->whereIn('status', $eligibleStatuses)->count(),
            'unassigned' => (clone $base)->where('status', TaxiBookingStatus::Confirmed->value)->count(),
            'active_trips' => (clone $base)->whereIn('status', $activeStatuses)->count(),
            'completed_today' => (clone $base)->where('status', TaxiBookingStatus::Completed->value)->whereDate('completed_at', $today)->count(),
            'drivers_available' => Driver::where('vendor_profile_id', $profile->id)->where('is_active', true)->where('availability_status', 'available')->count(),
            'vehicles_available' => Vehicle::where('vendor_profile_id', $profile->id)->where('is_active', true)->where('status', 'available')->count(),
        ];

        $todayRides = (clone $base)
            ->with(['assignedDriver:id,first_name,last_name'])
            ->whereDate('pickup_at', $today)
            ->orderBy('pickup_at')
            ->limit(10)
            ->get(['id', 'reference', 'customer_name', 'customer_phone', 'pickup_at', 'pickup_address', 'status', 'assigned_driver_id']);

        $upcomingRides = (clone $base)
            ->whereDate('pickup_at', '>', $today)
            ->whereIn('status', $eligibleStatuses)
            ->orderBy('pickup_at')
            ->limit(10)
            ->get(['id', 'reference', 'customer_name', 'pickup_at', 'pickup_address', 'status']);

        $unassignedRides = (clone $base)
            ->where('status', TaxiBookingStatus::Confirmed->value)
            ->orderBy('pickup_at')
            ->limit(10)
            ->get(['id', 'reference', 'customer_name', 'pickup_at', 'pickup_address']);

        return Inertia::render('Vendor/Taxi/Dashboard', [
            'cards' => $cards,
            'todayRides' => $todayRides,
            'upcomingRides' => $upcomingRides,
            'unassignedRides' => $unassignedRides,
        ]);
    }
}
