<?php

namespace App\Http\Controllers\Taxi;

use App\Enums\PaymentMethod;
use App\Enums\TaxiBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingCancellation;
use App\Models\TaxiBookingReschedule;
use App\Models\TaxiRefund;
use App\Services\TaxiCancellationService;
use App\Services\TaxiPaymentService;
use App\Services\TaxiRefundService;
use App\Services\TaxiRescheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxiChangeController extends Controller
{
    private function scope(Request $request, TaxiBooking $booking): string
    {
        if ($request->routeIs('account.*')) {
            abort_unless($booking->customer_user_id !== null && (int) $booking->customer_user_id === $request->user()->id, 404);

            return 'account';
        }
        if ($request->routeIs('vendor.*')) {
            $profile = $request->user()->vendorProfile;
            abort_unless($profile && $profile->is_active && (int) $booking->vendor_profile_id === $profile->id, 404);

            return 'vendor';
        }

        return 'admin';
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Account/Taxi/Index', ['bookings' => TaxiBooking::where('customer_user_id', $request->user()->id)
            ->latest('id')->paginate(15, ['id', 'reference', 'pickup_at', 'status', 'currency', 'total_amount'])]);
    }

    public function show(Request $request, TaxiBooking $booking): Response
    {
        $portal = $this->scope($request, $booking);
        $customer = $portal === 'account';
        $service = app(TaxiCancellationService::class);
        $cancellation = TaxiBookingCancellation::where('taxi_booking_id', $booking->id)->first();
        $canRefund = ! $customer && ($portal === 'vendor' || $request->user()->can('taxi.refunds.manage'));
        $seeRefunds = ! $customer && ($portal === 'vendor' || $request->user()->can('taxi.refunds.view') || $canRefund);
        $refunds = TaxiRefund::where('taxi_booking_id', $booking->id)->latest('id');
        $history = TaxiBookingReschedule::where('taxi_booking_id', $booking->id)->latest('id');

        return Inertia::render('Taxi/Changes/Show', [
            'portal' => $portal,
            'booking' => $booking->only(['id', 'reference', 'status', 'pickup_at', 'return_at', 'currency', 'total_amount']),
            'summary' => app(TaxiPaymentService::class)->summary($booking),
            'cancellation' => $customer ? $cancellation?->only(['status', 'cancellation_fee', 'refundable_amount', 'currency', 'cancelled_at']) : $cancellation,
            'refunds' => $customer ? $refunds->get(['refund_number', 'amount', 'currency', 'status', 'refunded_at']) : ($seeRefunds ? $refunds->with('items')->get() : []),
            'reschedules' => $customer ? $history->get(['old_pickup_at', 'new_pickup_at', 'old_return_at', 'new_return_at', 'created_at']) : $history->get(),
            'canCancel' => $service->enabled('cancellation.enabled') && $booking->status()->canTransitionTo(TaxiBookingStatus::Cancelled)
                && ($customer ? $service->enabled('customer_cancellation.enabled') : ($portal === 'vendor' || $request->user()->can('taxi.cancellations.manage'))),
            'canReschedule' => $service->enabled('reschedule.enabled') && in_array($booking->status, ['draft', 'quoted', 'confirmed', 'driver_assigned'], true)
                && ($customer ? $service->enabled('customer_reschedule.enabled') : ($portal === 'vendor' || $request->user()->can('taxi.reschedule.manage'))),
            'canRefund' => $canRefund && $service->enabled('refunds.enabled') && $cancellation !== null,
            'canNoShow' => ! $customer && $booking->status()->canTransitionTo(TaxiBookingStatus::NoShow) && ! $booking->pickup_at->isFuture()
                && ($portal === 'vendor' || $request->user()->can('taxi.cancellations.manage')),
            'reasons' => $customer ? ['customer_request', 'schedule_change', 'duplicate_booking', 'other'] : TaxiCancellationService::REASONS,
            'paymentMethods' => $canRefund ? collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()]) : [],
        ]);
    }

    public function quote(Request $request, TaxiBooking $booking): JsonResponse
    {
        $portal = $this->scope($request, $booking);
        $service = app(TaxiCancellationService::class);
        abort_if($portal === 'account' && (! $service->enabled('customer_cancellation.enabled') || $request->boolean('no_show')), 403);
        $quote = $service->quote($booking, $request->boolean('no_show'));
        if ($portal === 'account') {
            $quote = collect($quote)->only(['booking_total', 'amount_paid', 'cancellation_fee', 'refundable_amount', 'non_refundable_amount', 'currency', 'cutoff_state', 'calculated_at'])->all();
        }

        return response()->json($quote);
    }

    public function cancel(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $portal = $this->scope($request, $booking);
        $data = $request->validate(['reason_code' => ['required', Rule::in(TaxiCancellationService::REASONS)], 'reason' => ['nullable', 'string', 'max:500'], 'confirmed' => ['accepted']]);
        if ($portal === 'account') {
            abort_unless(in_array($data['reason_code'], ['customer_request', 'schedule_change', 'duplicate_booking', 'other'], true), 403);
        }
        app(TaxiCancellationService::class)->cancel($booking, $data['reason_code'], $data['reason'] ?? null, $request->user(), $portal === 'account' ? 'customer' : $portal);

        return back()->with('flash', 'Cancellation recorded. Any refund requires separate settlement.');
    }

    public function refund(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $this->scope($request, $booking);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99', 'decimal:0,2'], 'request_key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500']]);
        app(TaxiRefundService::class)->create($booking, (string) $data['amount'], $data['request_key'], $data['reason'], $request->user());

        return back()->with('flash', 'Refund reserved. Record settlement only after returning the funds.');
    }

    public function processRefund(Request $request, TaxiBooking $booking, TaxiRefund $refund): RedirectResponse
    {
        $this->scope($request, $booking);
        abort_unless($refund->taxi_booking_id === $booking->id && $refund->vendor_profile_id === $booking->vendor_profile_id, 404);
        $data = $request->validate(['method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))], 'reference' => ['required', 'string', 'max:100'], 'confirmed' => ['accepted']]);
        app(TaxiRefundService::class)->process($refund, $data['method'], $data['reference'], $request->user());

        return back()->with('flash', 'Manual refund settlement recorded.');
    }

    private function rescheduleData(Request $request, TaxiBooking $booking): array
    {
        $portal = $this->scope($request, $booking);
        abort_if($portal === 'account' && ! app(TaxiCancellationService::class)->enabled('customer_reschedule.enabled'), 403);

        return $request->validate(['pickup_at' => ['required', 'date'], 'return_at' => ['nullable', 'date'], 'reason' => ['required', 'string', 'max:500']]);
    }

    public function rescheduleQuote(Request $request, TaxiBooking $booking): JsonResponse
    {
        $data = $this->rescheduleData($request, $booking);

        return response()->json(app(TaxiRescheduleService::class)->quote($booking, $data['pickup_at'], $data['return_at'] ?? null));
    }

    public function reschedule(Request $request, TaxiBooking $booking): RedirectResponse
    {
        $data = $this->rescheduleData($request, $booking);
        $request->validate(['confirmed' => ['accepted']]);
        $history = app(TaxiRescheduleService::class)->reschedule($booking, $data['pickup_at'], $data['return_at'] ?? null, $data['reason'], $request->user());

        return back()->with('flash', $history ? 'Pickup rescheduled. The agreed price is unchanged.' : 'No change: the pickup already matches the requested time.');
    }
}
