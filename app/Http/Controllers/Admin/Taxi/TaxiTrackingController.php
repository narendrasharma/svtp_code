<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\VendorProfile;
use App\Services\TaxiDriverLocationService;
use App\Services\TaxiRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 12A.5 platform tracking board (no map yet).
 *
 * One row per operationally relevant driver: latest ping with
 * live/stale/offline freshness, current trip, vehicle. Latest positions
 * ride on a latestOfMany relation — full ping history is never loaded.
 */
class TaxiTrackingController extends Controller
{
    public function __construct(
        protected TaxiDriverLocationService $tracking,
        protected TaxiRouteService $routes,
    ) {}

    public function index(Request $request): Response
    {
        $drivers = Driver::with([
            'vendorProfile:id,business_name',
            'latestLocation',
            'assignments' => fn ($q) => $q->open()->with([
                'booking:id,reference,status,pickup_at,assigned_vehicle_id',
                'booking.assignedVehicle:id,name,registration_number',
            ])->latest('assigned_at'),
        ])
            ->where('is_active', true)
            ->where('employment_status', 'active')
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $drivers->getCollection()->transform(fn (Driver $driver): array => $this->tracking->driverRow($driver));

        return Inertia::render('Admin/Taxi/Tracking/Index', [
            'board' => $drivers,
            'filters' => $request->only(['search', 'vendor_id']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'staleSeconds' => $this->tracking->staleSeconds(),
            'map' => $this->routes->browserMapConfig(),
        ]);
    }

    /**
     * Trip map payload for one booking (markers + cached route).
     */
    public function route(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'taxi_booking' => ['required', 'integer', 'exists:taxi_bookings,id'],
        ]);

        $booking = TaxiBooking::findOrFail($validated['taxi_booking']);

        return response()->json($this->routes->tripMapPayload(
            $booking,
            $booking->assigned_driver_id
                ? $this->tracking->latestFor(Driver::findOrFail($booking->assigned_driver_id))
                : null,
        ));
    }
}
