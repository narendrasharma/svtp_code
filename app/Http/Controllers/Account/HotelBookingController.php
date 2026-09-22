<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Services\HotelBookingChangeService;
use App\Services\HotelReviewService;
use App\Support\HotelBookingTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelBookingController extends Controller
{
    public function __construct(protected HotelBookingChangeService $changes, protected HotelReviewService $reviews) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Account/HotelBookings/Index', [
            'bookings' => HotelBooking::where('user_id', $request->user()->id)->with('review:id,hotel_booking_id,status')->latest()->paginate(15)
                ->through(fn (HotelBooking $booking): array => [...$booking->toArray(), 'review' => $this->reviews->eligibility($booking, $request->user())]),
            'reviewsEnabled' => $this->reviews->enabled(),
        ]);
    }

    public function show(Request $request, HotelBooking $booking): Response
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        $booking->load('items.reservationNights', 'cancellations', 'hotelRefunds', 'changes');

        return Inertia::render('Account/HotelBookings/Show', [
            'booking' => $this->safe($booking), 'cancellationQuote' => $this->changes->cancellationQuote($booking),
            'reviewEligibility' => $this->reviews->eligibility($booking, $request->user()),
            'reviewsEnabled' => $this->reviews->enabled(),
        ]);
    }

    public function cancellationQuote(Request $request, HotelBooking $booking): JsonResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);

        return response()->json($this->changes->cancellationQuote($booking));
    }

    public function cancel(Request $request, HotelBooking $booking): RedirectResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:120'], 'reason_code' => ['nullable', 'in:customer_request,duplicate,other'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->changes->cancel($booking, $data['idempotency_key'], $request->user(), $data['reason_code'] ?? 'customer_request', $data['note'] ?? null);

        return back()->with('flash', 'Hotel booking cancelled. Any eligible refund is pending accounting review.');
    }

    public function rescheduleQuote(Request $request, HotelBooking $booking): JsonResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d']]);

        return response()->json($this->changes->rescheduleQuote($booking, $data['check_in'], $data['check_out']));
    }

    public function reschedule(Request $request, HotelBooking $booking): RedirectResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d'], 'quote_fingerprint' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'string', 'max:120']]);
        $this->changes->reschedule($booking, $data['idempotency_key'], $data['check_in'], $data['check_out'], $request->user(), $data['quote_fingerprint']);

        return back()->with('flash', 'Hotel booking rescheduled.');
    }

    /** @return array<string, mixed> */
    protected function safe(HotelBooking $booking): array
    {
        return ['id' => $booking->id, 'booking_number' => $booking->booking_number, 'property_name' => $booking->property_name_snapshot, 'status' => $booking->status->value, 'payment_status' => $booking->payment_status->value, 'currency' => $booking->currency, 'check_in' => $booking->check_in?->toDateString(), 'check_out' => $booking->check_out?->toDateString(), 'nights' => $booking->nights, 'rooms_count' => $booking->rooms_count, 'adults' => $booking->adults, 'children' => $booking->children, 'guest_name' => $booking->guest_name, 'guest_email' => $booking->guest_email, 'guest_phone' => $booking->guest_phone, 'special_requests' => $booking->special_requests, 'subtotal' => $booking->subtotal, 'taxes' => $booking->taxes, 'fees' => $booking->fees, 'total' => $booking->total, 'pricing_snapshot' => $booking->pricing_snapshot, 'items' => $booking->items->map(fn ($item): array => ['room_type' => $item->room_type_name_snapshot, 'rate_plan' => $item->rate_plan_name_snapshot, 'meal_plan' => $item->meal_plan_snapshot, 'cancellation_mode' => $item->cancellation_mode_snapshot, 'quantity' => $item->quantity, 'subtotal' => $item->subtotal, 'taxes' => $item->taxes, 'fees' => $item->fees, 'total' => $item->total, 'nightly' => $item->pricing_snapshot['nightly'] ?? []])->all(), 'timeline' => HotelBookingTimeline::for($booking)];
    }
}
