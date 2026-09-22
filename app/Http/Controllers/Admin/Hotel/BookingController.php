<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\User;
use App\Models\VendorProfile;
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

    public function create(): Response
    {
        return Inertia::render('Admin/Hotel/Bookings/Create', ['properties' => Property::published()->orderBy('name')->get(['id', 'name']), 'rooms' => HotelRoomType::active()->with('property:id,name')->orderBy('name')->get(['id', 'property_id', 'name']), 'plans' => HotelRatePlan::active()->orderBy('name')->get(['id', 'property_id', 'hotel_room_type_id', 'name', 'currency']), 'customers' => User::where('role', 'customer')->orderBy('name')->limit(500)->get(['id', 'name', 'email', 'phone'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id'], 'room_type_id' => ['required', 'integer', 'min:1'], 'rate_plan_id' => ['required', 'integer', 'min:1'], 'check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d'], 'rooms' => ['required', 'integer', 'min:1'], 'adults' => ['required', 'integer', 'min:1'], 'children' => ['sometimes', 'integer', 'min:0'], 'guest_name' => ['required', 'string', 'max:150'], 'guest_email' => ['required', 'email', 'max:150'], 'guest_phone' => ['required', 'string', 'max:40'], 'special_requests' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['nullable', 'string', 'max:120']]);
        $customer = ! empty($data['user_id']) ? User::findOrFail($data['user_id']) : null;
        $booking = $this->bookings->create($data + ['terms_accepted' => true], $customer, $request->user());

        return redirect()->route('admin.hotel.bookings.show', $booking)->with('flash', 'Hotel booking created.');
    }

    public function index(Request $request): Response
    {
        $request->validate([
            'booking_number' => ['nullable', 'string', 'max:40'],
            'customer' => ['nullable', 'string', 'max:150'],
            'property_id' => ['nullable', 'integer', 'min:1'],
            'vendor_profile_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:pending,confirmed,checked_in,checked_out,completed,cancelled,no_show'],
            'payment_status' => ['nullable', 'in:unpaid,partially_paid,paid,refunded,partially_refunded,failed'],
            'check_in_from' => ['nullable', 'date_format:Y-m-d'],
            'check_in_to' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $bookings = HotelBooking::query()
            ->with(['property:id,name', 'vendorProfile:id,business_name'])
            ->when($request->filled('booking_number'), fn ($q) => $q->where('booking_number', 'like', '%'.$request->string('booking_number').'%'))
            ->when($request->filled('customer'), fn ($q) => $q->where(function ($query) use ($request): void {
                $term = '%'.$request->string('customer').'%';
                $query->where('guest_name', 'like', $term)->orWhere('guest_email', 'like', $term)->orWhere('guest_phone', 'like', $term);
            }))
            ->when($request->filled('property_id'), fn ($q) => $q->where('property_id', $request->integer('property_id')))
            ->when($request->filled('vendor_profile_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_profile_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('check_in_from'), fn ($q) => $q->whereDate('check_in', '>=', $request->date('check_in_from')))
            ->when($request->filled('check_in_to'), fn ($q) => $q->whereDate('check_in', '<=', $request->date('check_in_to')))
            ->when($request->filled('created_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('created_from')))
            ->when($request->filled('created_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('created_to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Hotel/Bookings/Index', [
            'bookings' => $bookings,
            'statuses' => array_map(fn (HotelBookingStatus $status): array => ['value' => $status->value, 'label' => $status->label()], HotelBookingStatus::cases()),
            'paymentStatuses' => array_map(fn (HotelPaymentStatus $status): array => ['value' => $status->value, 'label' => str_replace('_', ' ', ucfirst($status->value))], HotelPaymentStatus::cases()),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name', 'vendor_profile_id']),
            'vendors' => VendorProfile::query()->whereHas('properties')->orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function show(HotelBooking $booking): Response
    {
        $booking->load(['property:id,name', 'vendorProfile:id,business_name', 'user:id,name,email,phone', 'items.reservationNights', 'cancellations', 'hotelRefunds', 'changes']);

        return Inertia::render('Admin/Hotel/Bookings/Show', ['booking' => $booking, 'timeline' => HotelBookingTimeline::for($booking), 'cancellationQuote' => $this->changes->cancellationQuote($booking)]);
    }

    public function status(Request $request, HotelBooking $booking): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:pending,confirmed,checked_in,checked_out,completed,cancelled,no_show']]);
        $this->bookings->changeStatus($booking, HotelBookingStatus::from($data['status']), $request->user());

        return back()->with('flash', 'Hotel booking status updated.');
    }

    public function cancel(Request $request, HotelBooking $booking): RedirectResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:120'], 'reason_code' => ['required', 'in:customer_request,property_issue,duplicate,no_show,other'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->changes->cancel($booking, $data['idempotency_key'], $request->user(), $data['reason_code'], $data['note'] ?? null);

        return back()->with('flash', 'Hotel booking cancelled.');
    }

    public function refund(Request $request, HotelBooking $booking): RedirectResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:120']]);
        $this->changes->createRefund($booking, $data['idempotency_key'], $request->user(), $booking->cancellations()->latest()->first());

        return back()->with('flash', 'Hotel refund accounting record created and is pending.');
    }

    public function reschedule(Request $request, HotelBooking $booking): RedirectResponse
    {
        $data = $request->validate(['check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d'], 'quote_fingerprint' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'string', 'max:120'], 'reason' => ['nullable', 'string', 'max:120']]);
        $this->changes->reschedule($booking, $data['idempotency_key'], $data['check_in'], $data['check_out'], $request->user(), $data['quote_fingerprint'], $data['reason'] ?? null);

        return back()->with('flash', 'Hotel booking rescheduled.');
    }

    public function rescheduleQuote(Request $request, HotelBooking $booking): JsonResponse
    {
        $data = $request->validate(['check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d']]);

        return response()->json($this->changes->rescheduleQuote($booking, $data['check_in'], $data['check_out']));
    }
}
