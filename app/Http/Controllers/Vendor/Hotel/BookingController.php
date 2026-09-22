<?php

namespace App\Http\Controllers\Vendor\Hotel;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Services\HotelBookingChangeService;
use App\Services\HotelBookingService;
use App\Support\HotelBookingTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(protected HotelBookingService $bookings, protected HotelBookingChangeService $changes) {}

    public function create(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        $properties = Property::where('vendor_profile_id', $profile->id)->published()->orderBy('name')->get(['id', 'name']);
        $rooms = HotelRoomType::whereIn('property_id', $properties->pluck('id'))->active()->orderBy('name')->get(['id', 'property_id', 'name']);
        $plans = HotelRatePlan::whereIn('property_id', $properties->pluck('id'))->active()->orderBy('name')->get(['id', 'property_id', 'hotel_room_type_id', 'name', 'currency']);

        return Inertia::render('Vendor/Hotel/Bookings/Create', compact('properties', 'rooms', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        $data = $request->validate(['room_type_id' => ['required', 'integer', 'min:1'], 'rate_plan_id' => ['required', 'integer', 'min:1'], 'check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d'], 'rooms' => ['required', 'integer', 'min:1'], 'adults' => ['required', 'integer', 'min:1'], 'children' => ['sometimes', 'integer', 'min:0'], 'guest_name' => ['required', 'string', 'max:150'], 'guest_email' => ['required', 'email', 'max:150'], 'guest_phone' => ['required', 'string', 'max:40'], 'special_requests' => ['nullable', 'string', 'max:2000']]);
        $plan = HotelRatePlan::with('property')->findOrFail($data['rate_plan_id']);
        abort_unless((int) $plan->property?->vendor_profile_id === (int) $profile->id, 404);
        $booking = $this->bookings->create($data + ['terms_accepted' => true], null, $request->user());

        return redirect()->route('vendor.hotel.bookings.show', $booking)->with('flash', 'Hotel booking created.');
    }

    public function index(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        $request->validate([
            'booking_number' => ['nullable', 'string', 'max:40'],
            'property_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:pending,confirmed,checked_in,checked_out,completed,cancelled,no_show'],
            'payment_status' => ['nullable', 'in:unpaid,partially_paid,paid,refunded,partially_refunded,failed'],
            'check_in_from' => ['nullable', 'date_format:Y-m-d'],
            'check_in_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $properties = Property::where('vendor_profile_id', $profile->id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Vendor/Hotel/Bookings/Index', [
            'bookings' => HotelBooking::where('vendor_profile_id', $profile->id)
                ->with('property:id,name')
                ->when($request->filled('booking_number'), fn ($q) => $q->where('booking_number', 'like', '%'.$request->string('booking_number').'%'))
                ->when($request->filled('property_id'), fn ($q) => $q->where('property_id', $request->integer('property_id')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')))
                ->when($request->filled('check_in_from'), fn ($q) => $q->whereDate('check_in', '>=', $request->date('check_in_from')))
                ->when($request->filled('check_in_to'), fn ($q) => $q->whereDate('check_in', '<=', $request->date('check_in_to')))
                ->latest()->paginate(20)->withQueryString(),
            'properties' => $properties,
            'statuses' => array_map(fn (HotelBookingStatus $status): array => ['value' => $status->value, 'label' => $status->label()], HotelBookingStatus::cases()),
            'paymentStatuses' => array_map(fn (HotelPaymentStatus $status): array => ['value' => $status->value, 'label' => str_replace('_', ' ', ucfirst($status->value))], HotelPaymentStatus::cases()),
        ]);
    }

    public function show(Request $request, HotelBooking $booking): Response
    {
        abort_unless($booking->vendor_profile_id && (int) $booking->vendor_profile_id === (int) $request->user()->vendorProfile?->id, 404);
        $booking->load(['property:id,name', 'items.reservationNights', 'cancellations', 'hotelRefunds', 'changes']);

        return Inertia::render('Vendor/Hotel/Bookings/Show', ['booking' => $booking, 'timeline' => HotelBookingTimeline::for($booking), 'cancellationQuote' => $this->changes->cancellationQuote($booking)]);
    }

    public function status(Request $request, HotelBooking $booking): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && (int) $booking->vendor_profile_id === (int) $profile->id, 404);
        $data = $request->validate(['status' => ['required', 'in:pending,confirmed,checked_in,checked_out,completed,cancelled,no_show']]);
        $this->bookings->changeStatus($booking, HotelBookingStatus::from($data['status']), $request->user());

        return back()->with('flash', 'Hotel booking status updated.');
    }

    public function cancel(Request $request, HotelBooking $booking): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && (int) $booking->vendor_profile_id === (int) $profile->id, 404);
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:120'], 'reason_code' => ['required', 'in:customer_request,property_issue,duplicate,no_show,other'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->changes->cancel($booking, $data['idempotency_key'], $request->user(), $data['reason_code'], $data['note'] ?? null);

        return back()->with('flash', 'Hotel booking cancelled.');
    }

    public function reschedule(Request $request, HotelBooking $booking): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && (int) $booking->vendor_profile_id === (int) $profile->id, 404);
        $data = $request->validate(['check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d'], 'quote_fingerprint' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'string', 'max:120']]);
        $this->changes->reschedule($booking, $data['idempotency_key'], $data['check_in'], $data['check_out'], $request->user(), $data['quote_fingerprint']);

        return back()->with('flash', 'Hotel booking rescheduled.');
    }

    public function rescheduleQuote(Request $request, HotelBooking $booking): JsonResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && (int) $booking->vendor_profile_id === (int) $profile->id, 404);
        $data = $request->validate(['check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d']]);

        return response()->json($this->changes->rescheduleQuote($booking, $data['check_in'], $data['check_out']));
    }
}
