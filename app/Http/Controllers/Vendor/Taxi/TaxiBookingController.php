<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taxi\StoreTaxiBookingRequest;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor taxi bookings: own profile only. Vendors execute their trips
 * (assign fleet, advance ride status); pricing authority stays with
 * the quoted totals recorded at creation.
 */
class TaxiBookingController extends Controller
{
    public function __construct(protected TaxiBookingService $bookings) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scoped(Request $request, TaxiBooking $booking): TaxiBooking
    {
        abort_unless((int) $booking->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $booking;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $bookings = TaxiBooking::with(['vehicleType:id,name', 'assignedDriver:id,first_name,last_name'])
            ->where('vendor_profile_id', $profile->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('reference', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('pickup_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Vendor/Taxi/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $request->only(['search', 'status']),
            'statuses' => collect(TaxiBookingStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->profile($request);

        return Inertia::render('Vendor/Taxi/Bookings/Form', [
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
            'fixedVendor' => true,
        ]);
    }

    public function store(StoreTaxiBookingRequest $request): RedirectResponse
    {
        $profile = $this->profile($request);

        $booking = $this->bookings->create(
            [...$request->bookingData(), 'vendor_profile_id' => $profile->id],
            $request->user(),
        );

        return redirect()->route('vendor.taxi.bookings.show', $booking)->with('flash', "Taxi booking {$booking->reference} confirmed.");
    }

    public function show(Request $request, TaxiBooking $booking): Response
    {
        $booking = $this->scoped($request, $booking);
        $booking->load([
            'vehicleType:id,name,passenger_capacity',
            'assignedDriver:id,first_name,last_name,phone',
            'assignedVehicle:id,name,registration_number',
            'stops', 'statusHistories.changer:id,name',
            'assignments.driver:id,first_name,last_name', 'assignments.vehicle:id,name,registration_number',
            'payments',
        ]);

        $profile = $this->profile($request);

        return Inertia::render('Vendor/Taxi/Bookings/Show', [
            'booking' => $booking,
            'summary' => $this->bookings->summary($booking),
            'fleet' => [
                'drivers' => Driver::where('vendor_profile_id', $profile->id)->where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'availability_status', 'employment_status']),
                'vehicles' => Vehicle::where('vendor_profile_id', $profile->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration_number', 'status', 'passenger_capacity']),
            ],
            'allowedTransitions' => collect($booking->status()->allowedTransitions())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function assign(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $booking = $this->scoped($request, $booking);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookings->assign(
            $booking,
            Driver::findOrFail($validated['driver_id']),
            Vehicle::findOrFail($validated['vehicle_id']),
            $request->user(),
            $validated['note'] ?? null,
        );

        return back()->with('flash', 'Driver and vehicle assigned.');
    }

    public function unassign(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $booking = $this->scoped($request, $booking);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->bookings->unassign($booking, $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Assignment removed.');
    }

    public function status(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $booking = $this->scoped($request, $booking);

        $validated = $request->validate([
            'status' => ['required', Rule::in(TaxiBookingStatus::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookings->changeStatus($booking, TaxiBookingStatus::from($validated['status']), $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Ride status updated.');
    }
}
