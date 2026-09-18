<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\TaxiBookingStatus;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taxi\StoreTaxiBookingRequest;
use App\Models\Driver;
use App\Models\Quotation;
use App\Models\TaxiBooking;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\TaxiBookingService;
use App\Services\TaxiPaymentService;
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
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'tripTypes' => collect(TripType::cases())
                ->filter(fn ($t) => in_array($t->value, TripType::bookable(), true))
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values(),
            'sources' => BookingSource::staffCreatable(),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->name]),
        ]);
    }

    public function store(StoreTaxiBookingRequest $request): RedirectResponse
    {
        $booking = $this->bookings->create($request->bookingData(), $request->user());

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
        );

        return redirect()->route('admin.taxi.bookings.show', $booking)->with('flash', "Taxi booking {$booking->reference} created from quotation.");
    }

    public function show(TaxiBooking $booking): Response
    {
        $booking->load([
            'customer:id,name,email,phone', 'vendorProfile:id,business_name',
            'lead:id,reference,name', 'quotation:id,reference,total_amount',
            'vehicleType:id,name,passenger_capacity',
            'assignedDriver:id,first_name,last_name,phone',
            'assignedVehicle:id,name,registration_number',
            'stops', 'statusHistories.changer:id,name',
            'assignments.driver:id,first_name,last_name', 'assignments.vehicle:id,name,registration_number', 'assignments.assigner:id,name',
            'payments.receiver:id,name',
        ]);

        $summary = $this->bookings->summary($booking);

        $fleet = $booking->vendor_profile_id ? [
            'drivers' => Driver::where('vendor_profile_id', $booking->vendor_profile_id)->where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'availability_status', 'employment_status']),
            'vehicles' => Vehicle::where('vendor_profile_id', $booking->vendor_profile_id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration_number', 'status', 'passenger_capacity']),
        ] : ['drivers' => [], 'vehicles' => []];

        return Inertia::render('Admin/Taxi/Bookings/Show', [
            'booking' => $booking,
            'summary' => $summary,
            'fleet' => $fleet,
            'statuses' => collect(TaxiBookingStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'allowedTransitions' => collect($booking->status()->allowedTransitions())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->name]),
            'canAssign' => request()->user()->can('taxi.bookings.assign'),
            'canStatus' => request()->user()->can('taxi.bookings.status'),
        ]);
    }

    public function assign(Request $request, TaxiBooking $booking): RedirectResponse
    {
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
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $this->bookings->unassign($booking, $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Assignment removed.');
    }

    public function status(Request $request, TaxiBooking $booking): RedirectResponse
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

        $this->bookings->changeStatus($booking, $to, $request->user(), $validated['note'] ?? null);

        return back()->with('flash', 'Ride status updated to '.$to->label().'.');
    }

    public function storePayment(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'payment_method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payment = $this->payments->recordPayment(
            $booking,
            (float) $validated['amount'],
            PaymentMethod::from($validated['payment_method']),
            $request->user(),
            $validated['note'] ?? null,
            $validated['external_reference'] ?? null,
        );

        $summary = $this->payments->summary($booking->refresh());

        return back()->with('flash', "Payment {$payment->reference} of ₹".number_format((float) $payment->amount, 2).' recorded. Outstanding ₹'.number_format($summary['due'], 2).'.');
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
