<?php

namespace App\Http\Controllers\Account;

use App\Enums\BookingStatus;
use App\Enums\CancellationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreCancellationRequest;
use App\Models\Booking;
use App\Services\BookingPaymentService;
use App\Services\CancellationService;
use App\Services\ReviewService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected CancellationService $cancellations, protected ReviewService $reviews) {}

    public function index(Request $request): Response
    {
        $bookings = $request->user()->bookings()
            ->with('package:id,title,slug')
            ->when($request->filled('search'), fn ($query) => $query->where(
                'booking_reference_id', 'like', '%'.$request->string('search')->toString().'%'
            ))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'booking_status', $request->string('status')->toString()
            ))
            ->when($request->input('scope') === 'upcoming', fn ($query) => $query
                ->whereDate('travel_date', '>=', today())
                ->where('booking_status', '!=', BookingStatus::Cancelled->value))
            ->when($request->input('scope') === 'past', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereDate('travel_date', '<', today())
                    ->orWhere('booking_status', BookingStatus::Cancelled->value)))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Account/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $request->only(['search', 'status', 'scope']),
            'statuses' => collect(BookingStatus::cases())->map(fn (BookingStatus $status): array => [
                'value' => $status->value, 'label' => $status->label(),
            ]),
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        $this->authorize('view', $booking);

        // Customer-visible shapes only: internal staff notes and internal
        // timeline entries never leave the admin area.
        $booking->load([
            'package',
            'statusHistories' => fn ($q) => $q->where('is_internal', false),
            'statusHistories.changer:id,name',
            'cancellationRequests.reviewer:id,name',
            'refunds',
            'bookingAddons',
            'payments',
            'reschedules',
            'notes' => fn ($q) => $q->where('is_internal', false),
        ]);

        // Customer-visible refund history: amounts/statuses only, no
        // internal ledger or admin actor details.
        $refunds = $booking->refunds->map(fn ($refund) => [
            'id' => $refund->id,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'status' => $refund->status instanceof \BackedEnum ? $refund->status->value : $refund->status,
            'reason' => $refund->reason,
            'processed_at' => $refund->processed_at,
        ]);

        return Inertia::render('Account/Bookings/Show', [
            'booking' => $booking,
            'pendingCancellation' => $booking->cancellationRequests
                ->firstWhere('status.value', CancellationStatus::Pending->value),
            'refunds' => $refunds,
            // Money position the customer may always see (their own
            // collections only — no commissions or ledger internals).
            'paymentSummary' => app(BookingPaymentService::class)->summary($booking),
            // Phase 9: verified-review eligibility scoped to this booking.
            'reviewEligibility' => $this->reviews->bookingEligibility($request->user(), $booking),
        ]);
    }

    public function requestCancellation(StoreCancellationRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('requestCancellation', $booking);

        $this->cancellations->request($booking, $request->user(), $request->input('reason'));

        return back()->with('flash', 'Cancellation request sent. Our team will review it shortly.');
    }
}
