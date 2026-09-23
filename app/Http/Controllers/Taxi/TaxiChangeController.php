<?php

namespace App\Http\Controllers\Taxi;

use App\Enums\PaymentMethod;
use App\Enums\TaxiBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingCancellation;
use App\Models\TaxiBookingReschedule;
use App\Models\TaxiRefund;
use App\Models\TaxiReview;
use App\Models\TaxiTrackingToken;
use App\Services\MoneyPresenter;
use App\Services\TaxiCancellationService;
use App\Services\TaxiPaymentService;
use App\Services\TaxiRefundService;
use App\Services\TaxiRescheduleService;
use App\Services\TaxiReviewService;
use App\Services\TaxiTrackingTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $scope = (string) $request->input('scope', 'all');
        $bookings = TaxiBooking::query()
            ->where('customer_user_id', $request->user()->id)
            ->with('vehicleType:id,name,passenger_capacity,luggage_capacity')
            ->when($scope === 'upcoming', fn ($query) => $query->where('pickup_at', '>=', now())->whereNotIn('status', ['cancelled', 'no_show', 'completed']))
            ->when($scope === 'past', fn ($query) => $query->where('pickup_at', '<', now())->whereNotIn('status', ['cancelled', 'no_show']))
            ->when($scope === 'cancelled', fn ($query) => $query->whereIn('status', ['cancelled', 'no_show']))
            ->latest('pickup_at')
            ->paginate(12)
            ->withQueryString();
        $bookings->getCollection()->transform(fn (TaxiBooking $booking): array => $this->card($booking));

        return Inertia::render('Account/Taxi/Index', ['bookings' => $bookings, 'filters' => ['scope' => $scope]]);
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

        if ($customer) {
            $booking->load([
                'vehicleType:id,name,passenger_capacity,luggage_capacity',
                'assignedVehicle:id,name,make,model',
                'assignedDriver:id,first_name,last_name',
                'statusHistories:id,taxi_booking_id,from_status,to_status,created_at',
                'payments:id,taxi_booking_id,reference,amount,currency,payment_method,paid_at',
            ]);
            $trackingToken = app(TaxiTrackingTokenService::class)->activeForBooking($booking);
            $review = TaxiReview::where('taxi_booking_id', $booking->id)
                ->where('customer_user_id', $request->user()->id)
                ->first();
            $reviewEligibility = app(TaxiReviewService::class)->eligibility($booking, $request->user());

            return Inertia::render('Account/Taxi/Show', [
                'booking' => $this->customerDetail($booking, $cancellation, $refunds->get(), $history->get(), $review, $trackingToken),
                'actions' => [
                    'can_cancel' => $service->enabled('cancellation.enabled') && $booking->status()->canTransitionTo(TaxiBookingStatus::Cancelled) && $service->enabled('customer_cancellation.enabled'),
                    'can_reschedule' => $service->enabled('reschedule.enabled') && in_array($booking->status, ['draft', 'quoted', 'confirmed', 'driver_assigned'], true) && $service->enabled('customer_reschedule.enabled'),
                    'can_review' => $reviewEligibility['eligible'],
                    'can_track' => $trackingToken !== null,
                ],
                'reasons' => ['customer_request', 'schedule_change', 'duplicate_booking', 'other'],
            ]);
        }

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

    private function card(TaxiBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'pickup_address' => $booking->pickup_address,
            'drop_address' => $booking->drop_address,
            'pickup_at' => $booking->pickup_at?->toISOString(),
            'passenger_count' => $booking->passenger_count,
            'luggage_count' => $booking->luggage_count,
            'vehicle' => $booking->vehicleType?->only(['name', 'passenger_capacity', 'luggage_capacity']),
            'currency' => $booking->currency,
            'total' => MoneyPresenter::present($booking->total_amount, $booking->currency),
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
        ];
    }

    /**
     * @param  Collection<int, TaxiRefund>  $refunds
     * @param  Collection<int, TaxiBookingReschedule>  $reschedules
     * @return array<string, mixed>
     */
    private function customerDetail(TaxiBooking $booking, ?TaxiBookingCancellation $cancellation, Collection $refunds, Collection $reschedules, ?TaxiReview $review, ?TaxiTrackingToken $trackingToken): array
    {
        $paymentSummary = app(TaxiPaymentService::class)->summary($booking);
        $snapshot = is_array($booking->pricing_snapshot) ? $booking->pricing_snapshot : [];
        $breakdown = collect($snapshot['breakdown'] ?? [
            'base_amount' => $booking->base_amount,
            'extra_amount' => $booking->extra_amount,
            'discount_amount' => $booking->discount_amount,
            'tax_amount' => $booking->tax_amount,
            'grand_total' => $booking->total_amount,
        ])->mapWithKeys(fn ($amount, $key): array => [$key => MoneyPresenter::present($amount, $booking->currency)])->all();

        return [
            ...$this->card($booking),
            'trip_type' => $booking->trip_type,
            'return_at' => $booking->return_at?->toISOString(),
            'pickup_lat' => $booking->pickup_lat,
            'pickup_lng' => $booking->pickup_lng,
            'drop_lat' => $booking->drop_lat,
            'drop_lng' => $booking->drop_lng,
            'customer' => ['name' => $booking->customer_name, 'phone' => $booking->customer_phone, 'email' => $booking->customer_email],
            'vehicle' => $booking->vehicleType?->only(['name', 'passenger_capacity', 'luggage_capacity']),
            'assigned_vehicle' => $booking->assignedVehicle ? ['name' => $booking->assignedVehicle->name, 'make_model' => trim(($booking->assignedVehicle->make ?? '').' '.($booking->assignedVehicle->model ?? '')) ?: null] : null,
            'assigned_driver' => $booking->assignedDriver && $booking->status !== TaxiBookingStatus::Confirmed->value ? ['display_name' => trim($booking->assignedDriver->first_name.' '.($booking->assignedDriver->last_name ?? ''))] : null,
            'pricing' => ['breakdown' => $breakdown, 'total' => MoneyPresenter::present($booking->total_amount, $booking->currency), 'snapshot_calculated_at' => $snapshot['calculated_at'] ?? null],
            'payment' => ['total' => MoneyPresenter::present($paymentSummary['total'], $booking->currency), 'paid' => MoneyPresenter::present($paymentSummary['paid'], $booking->currency), 'due' => MoneyPresenter::present($paymentSummary['due'], $booking->currency)],
            'timestamps' => ['booked_at' => $booking->created_at?->toISOString(), 'confirmed_at' => $booking->confirmed_at?->toISOString(), 'completed_at' => $booking->completed_at?->toISOString(), 'cancelled_at' => $booking->cancelled_at?->toISOString()],
            'history' => $booking->statusHistories->map(fn ($item): array => ['from' => $item->from_status, 'to' => $item->to_status, 'at' => $item->created_at?->toISOString()])->values()->all(),
            'cancellation' => $cancellation ? ['status' => $cancellation->status, 'fee' => MoneyPresenter::present($cancellation->cancellation_fee, $cancellation->currency), 'refundable' => MoneyPresenter::present($cancellation->refundable_amount, $cancellation->currency), 'cancelled_at' => $cancellation->cancelled_at?->toISOString()] : null,
            'refunds' => $refunds->map(fn (TaxiRefund $refund): array => ['reference' => $refund->refund_number, 'amount' => MoneyPresenter::present($refund->amount, $refund->currency), 'status' => $refund->status, 'refunded_at' => $refund->refunded_at?->toISOString()])->values()->all(),
            'reschedules' => $reschedules->map(fn (TaxiBookingReschedule $change): array => ['old_pickup_at' => $change->old_pickup_at?->toISOString(), 'new_pickup_at' => $change->new_pickup_at?->toISOString(), 'reason' => $change->reason])->values()->all(),
            'review' => $review ? ['overall_rating' => $review->overall_rating, 'status' => $review->status, 'comment' => $review->comment, 'submitted_at' => $review->submitted_at?->toISOString(), 'vendor_reply' => $review->vendor_reply] : null,
            'tracking' => ['available' => $trackingToken !== null, 'expires_at' => $trackingToken?->expires_at?->toISOString()],
            'special_instructions' => $booking->special_instructions,
            'quoted_distance_km' => $booking->quoted_distance_km,
            'quoted_duration_minutes' => $booking->quoted_duration_minutes,
        ];
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
