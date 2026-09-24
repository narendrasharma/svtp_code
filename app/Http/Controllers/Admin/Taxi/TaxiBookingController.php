<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\TaxiBookingStatus;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taxi\QuoteTaxiPricingRequest;
use App\Http\Requests\Taxi\StoreTaxiBookingRequest;
use App\Models\Driver;
use App\Models\Quotation;
use App\Models\TaxiBooking;
use App\Models\TaxiRentalPackage;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\TaxiAutoDispatchService;
use App\Services\TaxiBookingService;
use App\Services\TaxiPaymentService;
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
 * Admin taxi bookings (12A.1): One-Way + Airport Transfer.
 * Fast desk flow, assignment, ride status, manual payments.
 */
class TaxiBookingController extends Controller
{
    public function __construct(
        protected TaxiBookingService $bookings,
        protected TaxiPaymentService $payments,
        protected TaxiPricingService $pricing,
        protected TaxiAutoDispatchService $autoDispatch,
        protected TaxiTrackingTokenService $trackingTokens,
    ) {}

    public function index(Request $request): Response
    {
        $bookings = TaxiBooking::with(['vendorProfile:id,business_name', 'vehicleType:id,name', 'assignedDriver:id,first_name,last_name'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('reference', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhere('pickup_address', 'like', $term)
                    ->orWhere('drop_address', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('trip_type'), fn ($q) => $q->where('trip_type', $request->string('trip_type')->toString()))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('pickup_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('pickup_at', '<=', $request->date('date_to')))
            ->latest('pickup_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Taxi/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $request->only(['search', 'status', 'trip_type', 'vendor_id', 'date_from', 'date_to']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'statuses' => collect(TaxiBookingStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'tripTypes' => collect(TripType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Taxi/Bookings/Form', [
            'adminBreadcrumbs' => [
                ['label' => 'Dashboard', 'href' => '/admin/dashboard'],
                ['label' => 'Taxi', 'href' => '/admin/taxi/dashboard'],
                ['label' => 'Bookings', 'href' => '/admin/taxi/bookings'],
                ['label' => 'New Taxi Booking'],
            ],
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'tripTypes' => collect(TripType::cases())
                ->filter(fn ($t) => in_array($t->value, TripType::bookable(), true))
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values(),
            'sources' => BookingSource::staffCreatable(),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->name]),
            'rentalPackages' => TaxiRentalPackage::with('rateCard:id,name,vendor_profile_id,vehicle_type_id,currency')
                ->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function quote(QuoteTaxiPricingRequest $request): JsonResponse
    {
        $quote = $this->pricing->quote([
            ...$request->validated(),
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
        $booking = $this->bookings->create($request->bookingData(), $request->user(), authorizedActualCosts: true);

        return redirect()->route('admin.taxi.bookings.show', $booking)->with('flash', "Taxi booking {$booking->reference} confirmed.");
    }

    /**
     * Convert an accepted taxi quotation into a taxi booking. Trip
     * details come from the desk payload; customer/lead and quoted
     * totals default from the quotation. Tour conversion untouched.
     */
    public function convert(StoreTaxiBookingRequest $request): RedirectResponse
    {
        $quotationId = $request->validate([
            'quotation_id' => ['required', 'integer', 'exists:quotations,id'],
        ])['quotation_id'];

        $booking = $this->bookings->createFromQuotation(
            Quotation::findOrFail($quotationId),
            $request->bookingData(),
            $request->user(),
            authorizedActualCosts: true,
        );

        return redirect()->route('admin.taxi.bookings.show', $booking)->with('flash', "Taxi booking {$booking->reference} created from quotation.");
    }

    public function show(TaxiBooking $taxiBooking): Response
    {
        $taxiBooking->load([
            'customer:id,name,email,phone', 'vendorProfile:id,business_name',
            'lead:id,reference,name', 'quotation:id,reference,total_amount',
            'vehicleType:id,name,passenger_capacity',
            'assignedDriver:id,first_name,last_name,phone',
            'assignedVehicle:id,name,registration_number',
            'stops', 'statusHistories.changer:id,name',
            'assignments.driver:id,first_name,last_name', 'assignments.vehicle:id,name,registration_number', 'assignments.assigner:id,name',
            'payments.receiver:id,name',
        ]);

        $summary = $this->bookings->summary($taxiBooking);

        $fleet = $taxiBooking->vendor_profile_id ? [
            'drivers' => Driver::where('vendor_profile_id', $taxiBooking->vendor_profile_id)->where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'availability_status', 'employment_status']),
            'vehicles' => Vehicle::where('vendor_profile_id', $taxiBooking->vendor_profile_id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration_number', 'status', 'passenger_capacity']),
        ] : ['drivers' => [], 'vehicles' => []];

        return Inertia::render('Admin/Taxi/Bookings/Show', [
            'booking' => $taxiBooking,
            'summary' => $summary,
            'fleet' => $fleet,
            'statuses' => collect(TaxiBookingStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'allowedTransitions' => collect($taxiBooking->status()->allowedTransitions())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->name]),
            'canAssign' => request()->user()->can('taxi.bookings.assign'),
            'canStatus' => request()->user()->can('taxi.bookings.status'),
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
        $offer = $this->autoDispatch->startForBooking($taxiBooking, $request->user(), 'manual');

        return back()->with('flash', "Auto-dispatch started — offered to {$offer->driver->fullName()}.");
    }

    public function stopAutoDispatch(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
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
        $result = $this->trackingTokens->generate($taxiBooking, $request->user(), 'manual');

        return back()->with('tracking_url', $result['url'])->with('flash', 'Customer tracking link generated.');
    }

    public function revokeTrackingLink(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $token = $this->trackingTokens->activeForBooking($taxiBooking);

        if ($token !== null) {
            $this->trackingTokens->revoke($token, $request->user());
        }

        return back()->with('flash', 'Customer tracking link revoked.');
    }

    public function assign(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
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
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $this->bookings->unassign($taxiBooking, $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Assignment removed.');
    }

    public function status(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(TaxiBookingStatus::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $to = TaxiBookingStatus::from($validated['status']);

        if (in_array($to, [TaxiBookingStatus::Cancelled, TaxiBookingStatus::NoShow], true)
            && ! $request->user()->can('taxi.bookings.cancel')) {
            abort(403, 'Cancellation requires the taxi cancellation permission.');
        }

        $this->bookings->changeStatus($taxiBooking, $to, $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Ride status updated to '.$to->label().'.');
    }

    public function storePayment(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'payment_method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payment = $this->payments->recordPayment(
            $taxiBooking,
            (float) $validated['amount'],
            PaymentMethod::from($validated['payment_method']),
            $request->user(),
            $validated['note'] ?? null,
            $validated['external_reference'] ?? null,
        );

        $summary = $this->payments->summary($taxiBooking->refresh());

        return back()->with('flash', "Payment {$payment->reference} of {$summary['currency']} ".number_format((float) $payment->amount, 2)." recorded. Outstanding {$summary['currency']} ".number_format($summary['due'], 2).'.');
    }

    /**
     * Shared payload for reuse (e.g. vendor-side creation flows that
     * force their own vendor scope). Validation lives in
     * StoreTaxiBookingRequest.
     *
     * @return array<string, mixed>
     */
    public function bookingPayload(StoreTaxiBookingRequest $request): array
    {
        return $request->bookingData();
    }
}
