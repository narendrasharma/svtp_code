<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\BookingSource;
use App\Enums\TaxiBookingStatus;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taxi\QuoteTaxiPricingRequest;
use App\Http\Requests\Taxi\StoreTaxiBookingRequest;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\TaxiRentalPackage;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\TaxiAutoDispatchService;
use App\Services\TaxiBookingService;
use App\Services\TaxiPricingService;
use App\Services\TaxiTrackingTokenService;
use App\Support\TaxiSettings;
use Illuminate\Http\JsonResponse;
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
    public function __construct(
        protected TaxiBookingService $bookings,
        protected TaxiPricingService $pricing,
        protected TaxiAutoDispatchService $autoDispatch,
        protected TaxiTrackingTokenService $trackingTokens,
    ) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scoped(Request $request, TaxiBooking $taxiBooking): TaxiBooking
    {
        abort_unless((int) $taxiBooking->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $taxiBooking;
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
            'tripTypes' => collect(TripType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()]),
            'rentalPackages' => TaxiRentalPackage::with('rateCard:id,name,vendor_profile_id,vehicle_type_id,currency')
                ->where('is_active', true)
                ->whereHas('rateCard', fn ($query) => $query->where('is_active', true)->where(fn ($scope) => $scope->whereNull('vendor_profile_id')->orWhere('vendor_profile_id', $this->profile($request)->id)))
                ->orderBy('sort_order')->get(),
        ]);
    }

    public function quote(QuoteTaxiPricingRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $quote = $this->pricing->quote([
            ...$request->validated(),
            'vendor_profile_id' => $profile->id,
            'currency' => TaxiSettings::get('taxi.default_currency') ?? 'INR',
            'distance_km' => $request->validated('quoted_distance_km') ?? 0,
            'duration_minutes' => $request->validated('quoted_duration_minutes') ?? 0,
            'authorized_actual_costs' => true,
        ]);

        return response()->json([
            'currency' => $quote['currency'],
            'breakdown' => $quote['breakdown'],
            'rate_card' => $quote['snapshot']['rate_card'],
            'rental_package' => $quote['snapshot']['rental_package'],
        ]);
    }

    public function store(StoreTaxiBookingRequest $request): RedirectResponse
    {
        $profile = $this->profile($request);

        $booking = $this->bookings->create(
            [...$request->bookingData(), 'vendor_profile_id' => $profile->id, 'source' => BookingSource::Vendor->value],
            $request->user(),
            authorizedActualCosts: true,
        );

        return redirect()->route('vendor.taxi.bookings.show', $booking)->with('flash', "Taxi booking {$booking->reference} confirmed.");
    }

    public function show(Request $request, TaxiBooking $taxiBooking): Response
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);
        $taxiBooking->load([
            'vehicleType:id,name,passenger_capacity',
            'assignedDriver:id,first_name,last_name,phone',
            'assignedVehicle:id,name,registration_number',
            'stops', 'statusHistories.changer:id,name',
            'assignments.driver:id,first_name,last_name', 'assignments.vehicle:id,name,registration_number',
            'payments',
        ]);

        $profile = $this->profile($request);

        return Inertia::render('Vendor/Taxi/Bookings/Show', [
            'booking' => $taxiBooking,
            'summary' => $this->bookings->summary($taxiBooking),
            'fleet' => [
                'drivers' => Driver::where('vendor_profile_id', $profile->id)->where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'availability_status', 'employment_status']),
                'vehicles' => Vehicle::where('vendor_profile_id', $profile->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration_number', 'status', 'passenger_capacity']),
            ],
            'allowedTransitions' => collect($taxiBooking->status()->allowedTransitions())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'autoDispatch' => $this->autoDispatchPanel($taxiBooking),
            'trackingLink' => $this->trackingLinkPanel($taxiBooking),
        ]);
    }

    /**
     * @return array{status:string, attempts:int, max_attempts:int, pending:?array{id:int, driver_name:string, vehicle_name:string, rank:int, expires_at:?string}, history:array<int, mixed>}
     */
    protected function autoDispatchPanel(TaxiBooking $taxiBooking): array
    {
        $pending = $this->autoDispatch->pendingFor($taxiBooking);
        $pending?->load(['driver:id,first_name,last_name', 'vehicle:id,name,registration_number']);

        return [
            'status' => $this->autoDispatch->statusFor($taxiBooking),
            'attempts' => $this->autoDispatch->attemptsFor($taxiBooking),
            'max_attempts' => $this->autoDispatch->maxAttempts(),
            'pending' => $pending ? [
                'id' => $pending->id,
                'driver_name' => $pending->driver?->fullName(),
                'vehicle_name' => $pending->vehicle ? $pending->vehicle->name.' ('.$pending->vehicle->registration_number.')' : null,
                'rank' => $pending->rank,
                'expires_at' => $pending->expires_at?->toISOString(),
            ] : null,
            'history' => $taxiBooking->dispatchOffers()->with(['driver:id,first_name,last_name'])->latest('offered_at')->limit(10)->get(),
        ];
    }

    public function startAutoDispatch(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $offer = $this->autoDispatch->startForBooking($taxiBooking, $request->user(), 'manual');

        return back()->with('flash', "Auto-dispatch started — offered to {$offer->driver->fullName()}.");
    }

    public function stopAutoDispatch(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $this->autoDispatch->stopForBooking($taxiBooking, $request->user());

        return back()->with('flash', 'Auto-dispatch stopped — the booking stays in the manual queue.');
    }

    /**
     * @return array{active:bool, expires_at:?string, last_accessed_at:?string}
     */
    protected function trackingLinkPanel(TaxiBooking $taxiBooking): array
    {
        $token = $this->trackingTokens->activeForBooking($taxiBooking);

        return [
            'active' => $token !== null,
            'expires_at' => $token?->expires_at?->toISOString(),
            'last_accessed_at' => $token?->last_accessed_at?->toISOString(),
        ];
    }

    public function generateTrackingLink(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $result = $this->trackingTokens->generate($taxiBooking, $request->user(), 'manual');

        return back()->with('tracking_url', $result['url'])->with('flash', 'Customer tracking link generated.');
    }

    public function revokeTrackingLink(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $token = $this->trackingTokens->activeForBooking($taxiBooking);

        if ($token !== null) {
            $this->trackingTokens->revoke($token, $request->user());
        }

        return back()->with('flash', 'Customer tracking link revoked.');
    }

    public function assign(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookings->assign(
            $taxiBooking,
            Driver::findOrFail($validated['driver_id']),
            Vehicle::findOrFail($validated['vehicle_id']),
            $request->user(),
            $validated['note'] ?? null,
        );

        return back()->with('flash', 'Driver and vehicle assigned.');
    }

    public function unassign(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->bookings->unassign($taxiBooking, $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Assignment removed.');
    }

    public function status(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $validated = $request->validate([
            'status' => ['required', Rule::in(TaxiBookingStatus::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookings->changeStatus($taxiBooking, TaxiBookingStatus::from($validated['status']), $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Ride status updated.');
    }
}
