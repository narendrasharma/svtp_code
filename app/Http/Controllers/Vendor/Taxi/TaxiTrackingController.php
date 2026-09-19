<?php

namespace App\Http\Controllers\Vendor\Taxi;

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
 * Phase 12A.5 vendor tracking board (own drivers only, no map yet).
 *
 * Server-side vendor scoping — the frontend never filters locations.
 */
class TaxiTrackingController extends Controller
{
    public function __construct(
        protected TaxiDriverLocationService $tracking,
        protected TaxiRouteService $routes,
    ) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $drivers = Driver::with([
            'vendorProfile:id,business_name',
            'latestLocation',
            'assignments' => fn ($q) => $q->open()->with([
                'booking:id,reference,status,pickup_at,assigned_vehicle_id',
                'booking.assignedVehicle:id,name,registration_number',
            ])->latest('assigned_at'),
        ])
            ->where('vendor_profile_id', $profile->id)
            ->where('is_active', true)
            ->where('employment_status', 'active')
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

        return Inertia::render('Vendor/Taxi/Tracking/Index', [
            'board' => $drivers,
            'filters' => $request->only(['search']),
            'staleSeconds' => $this->tracking->staleSeconds(),
            'map' => $this->routes->browserMapConfig(),
        ]);
    }

    /**
     * Trip map payload for one OWN booking. Cross-vendor ids 404.
     */
    public function route(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'taxi_booking' => ['required', 'integer', 'exists:taxi_bookings,id'],
        ]);

        $booking = TaxiBooking::findOrFail($validated['taxi_booking']);

        abort_unless((int) $booking->vendor_profile_id === (int) $profile->id, 404);

        return response()->json($this->routes->tripMapPayload(
            $booking,
            $booking->assigned_driver_id
                ? $this->tracking->latestFor(Driver::findOrFail($booking->assigned_driver_id))
                : null,
        ));
    }
}
