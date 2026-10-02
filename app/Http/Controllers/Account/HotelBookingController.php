<?php

namespace App\Http\Controllers\Account;

use App\Enums\HotelBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelBookingChange;
use App\Models\HotelBookingRefund;
use App\Services\HotelBookingChangeService;
use App\Services\HotelReviewService;
use App\Services\MoneyPresenter;
use App\Support\HotelBookingTimeline;
use App\Support\HotelSettings;
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
        $scope = $request->string('scope', 'all')->toString();
        $allowedScopes = ['all', 'upcoming', 'current', 'past', 'cancelled'];

        if (! in_array($scope, $allowedScopes, true)) {
            $scope = 'all';
        }

        $bookings = HotelBooking::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'review:id,hotel_booking_id,status',
                'items:id,hotel_booking_id,room_type_name_snapshot,rate_plan_name_snapshot,quantity',
                'property:id,slug',
                'property.images:id,property_id,path,alt_text,sort_order,is_primary',
            ])
            ->when($scope === 'upcoming', fn ($query) => $query
                ->whereIn('status', [HotelBookingStatus::Pending->value, HotelBookingStatus::Confirmed->value])
                ->whereDate('check_out', '>=', today()))
            ->when($scope === 'current', fn ($query) => $query->where('status', HotelBookingStatus::CheckedIn->value))
            ->when($scope === 'past', fn ($query) => $query->whereIn('status', [
                HotelBookingStatus::CheckedOut->value,
                HotelBookingStatus::Completed->value,
                HotelBookingStatus::NoShow->value,
            ]))
            ->when($scope === 'cancelled', fn ($query) => $query->where('status', HotelBookingStatus::Cancelled->value))
            ->orderByRaw("CASE WHEN status IN ('pending', 'confirmed', 'checked_in') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status IN ('pending', 'confirmed', 'checked_in') THEN check_in END ASC")
            ->orderByDesc('booked_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (HotelBooking $booking): array => $this->listSafe($booking, $request));

        return Inertia::render('Account/HotelBookings/Index', [
            'bookings' => $bookings,
            'filters' => ['scope' => $scope],
            'reviewsEnabled' => $this->reviews->enabled(),
        ]);
    }

    public function show(Request $request, HotelBooking $booking): Response
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        $booking->load([
            'property:id,slug',
            'property.images:id,property_id,path,alt_text,sort_order,is_primary',
            'items.reservationNights',
            'cancellations',
            'hotelRefunds',
            'changes',
            'review',
        ]);
        $cancellationQuote = $this->changes->cancellationQuote($booking);
        $reviewEligibility = $this->reviews->eligibility($booking, $request->user());

        return Inertia::render('Account/HotelBookings/Show', [
            'booking' => $this->safe($booking, $cancellationQuote, $reviewEligibility),
            'cancellationQuote' => $this->presentCancellationQuote($cancellationQuote, $booking->currency),
            'reviewEligibility' => $reviewEligibility,
            'reviewsEnabled' => $this->reviews->enabled(),
        ]);
    }

    public function confirmation(Request $request, HotelBooking $booking): Response
    {
        abort_unless($request->user() && (int) $booking->user_id === (int) $request->user()->id || ! $booking->user_id && (int) $request->session()->get('hotel_booking_confirmation_id') === (int) $booking->id, 404);
        $booking->load('property', 'items.reservationNights');

        return Inertia::render('Hotels/Confirmation', [
            'booking' => $this->safe($booking),
            'canViewBooking' => $request->user() && (int) $booking->user_id === (int) $request->user()->id,
            'seo' => [
                'title' => __('common.booking_confirmed').' · '.$booking->booking_number,
                'description' => __('common.booking_confirmation_description'),
            ],
        ]);
    }

    public function cancellationQuote(Request $request, HotelBooking $booking): JsonResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);

        return response()->json($this->presentCancellationQuote(
            $this->changes->cancellationQuote($booking),
            $booking->currency,
        ));
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

        return response()->json($this->presentRescheduleQuote(
            $this->changes->rescheduleQuote($booking, $data['check_in'], $data['check_out']),
            $booking->currency,
        ));
    }

    public function reschedule(Request $request, HotelBooking $booking): RedirectResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d'], 'quote_fingerprint' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'string', 'max:120']]);
        $this->changes->reschedule($booking, $data['idempotency_key'], $data['check_in'], $data['check_out'], $request->user(), $data['quote_fingerprint']);

        return back()->with('flash', 'Hotel booking rescheduled.');
    }

    /** @return array<string, mixed> */
    protected function listSafe(HotelBooking $booking, Request $request): array
    {
        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'property_name' => $booking->property_name_snapshot,
            'property_slug' => $booking->property?->slug,
            'property_image' => $this->propertyImage($booking),
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status->value,
            'currency' => $booking->currency,
            'check_in' => $booking->check_in?->toDateString(),
            'check_out' => $booking->check_out?->toDateString(),
            'nights' => $booking->nights,
            'rooms_count' => $booking->rooms_count,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'room_type' => $booking->items->first()?->room_type_name_snapshot,
            'room_quantity' => $booking->items->first()?->quantity,
            'display_money' => ['total' => MoneyPresenter::present($booking->total, $booking->currency)],
            'review' => $this->reviews->eligibility($booking, $request->user()),
        ];
    }

    /** @param array<string, mixed>|null $cancellationQuote @param array<string, mixed>|null $reviewEligibility */
    protected function safe(HotelBooking $booking, ?array $cancellationQuote = null, ?array $reviewEligibility = null): array
    {
        $cancellationQuote ??= ['eligible' => false];
        $reviewEligibility ??= ['can_review' => false, 'has_review' => false];

        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'property_name' => $booking->property_name_snapshot,
            'property_slug' => $booking->property?->slug,
            'property_image' => $this->propertyImage($booking),
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status->value,
            'currency' => $booking->currency,
            'check_in' => $booking->check_in?->toDateString(),
            'check_out' => $booking->check_out?->toDateString(),
            'nights' => $booking->nights,
            'rooms_count' => $booking->rooms_count,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'special_requests' => $booking->special_requests,
            'booked_at' => $booking->booked_at?->toIso8601String(),
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'completed_at' => $booking->completed_at?->toIso8601String(),
            'subtotal' => $booking->subtotal,
            'taxes' => $booking->taxes,
            'fees' => $booking->fees,
            'total' => $booking->total,
            'display_money' => [
                'subtotal' => MoneyPresenter::present($booking->subtotal, $booking->currency),
                'taxes' => MoneyPresenter::present($booking->taxes, $booking->currency),
                'fees' => MoneyPresenter::present($booking->fees, $booking->currency),
                'total' => MoneyPresenter::present($booking->total, $booking->currency),
            ],
            'cancellation_policy' => $this->safePolicy($booking->pricing_snapshot['cancellation_policy'] ?? [
                'mode' => $booking->pricing_snapshot['cancellation_mode'] ?? null,
            ]),
            'items' => $booking->items->map(fn ($item): array => [
                'room_type' => $item->room_type_name_snapshot,
                'rate_plan' => $item->rate_plan_name_snapshot,
                'meal_plan' => $item->meal_plan_snapshot,
                'cancellation_mode' => $item->cancellation_mode_snapshot,
                'quantity' => $item->quantity,
                'adults' => $item->adults,
                'children' => $item->children,
                'check_in' => $item->check_in?->toDateString(),
                'check_out' => $item->check_out?->toDateString(),
                'nights' => $item->nights,
                'subtotal' => $item->subtotal,
                'taxes' => $item->taxes,
                'fees' => $item->fees,
                'total' => $item->total,
                'display_money' => ['total' => MoneyPresenter::present($item->total, $booking->currency)],
            ])->all(),
            'cancellations' => $booking->cancellations->map(fn ($cancellation): array => [
                'currency' => $cancellation->currency,
                'cancellation_fee' => $cancellation->cancellation_fee,
                'refundable_amount' => $cancellation->refundable_amount,
                'display_money' => [
                    'fee' => MoneyPresenter::present($cancellation->cancellation_fee, $cancellation->currency),
                    'refundable' => MoneyPresenter::present($cancellation->refundable_amount, $cancellation->currency),
                ],
                'policy' => $this->safePolicy($cancellation->policy_snapshot),
                'cancelled_at' => $cancellation->cancelled_at?->toIso8601String(),
            ])->all(),
            'refunds' => $booking->hotelRefunds->map(fn (HotelBookingRefund $refund): array => [
                'refund_number' => $refund->refund_number,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'status' => $refund->status,
                'reason' => $refund->reason,
                'payment_reference' => $refund->payment_reference,
                'requested_at' => $refund->requested_at?->toIso8601String(),
                'completed_at' => $refund->completed_at?->toIso8601String(),
                'display_money' => ['amount' => MoneyPresenter::present($refund->amount, $refund->currency)],
            ])->values()->all(),
            'changes' => $booking->changes->map(fn (HotelBookingChange $change): array => [
                'old_check_in' => $change->old_check_in?->toDateString(),
                'old_check_out' => $change->old_check_out?->toDateString(),
                'new_check_in' => $change->new_check_in?->toDateString(),
                'new_check_out' => $change->new_check_out?->toDateString(),
                'old_total' => $change->old_total,
                'new_total' => $change->new_total,
                'difference' => $change->difference,
                'currency' => $change->currency,
                'status' => $change->status,
                'reason' => $change->reason,
                'created_at' => $change->created_at?->toIso8601String(),
                'display_money' => [
                    'old_total' => MoneyPresenter::present($change->old_total, $change->currency),
                    'new_total' => MoneyPresenter::present($change->new_total, $change->currency),
                    'difference' => MoneyPresenter::present(abs((float) $change->difference), $change->currency),
                ],
            ])->values()->all(),
            'review' => $booking->review ? [
                ...$booking->review->publicPayload(),
                'status' => $booking->review->status,
                'rejection_reason' => $booking->review->rejection_reason,
            ] : null,
            'timeline' => HotelBookingTimeline::for($booking),
            'actions' => [
                'can_cancel' => HotelSettings::enabled('hotel.cancellation.enabled') && (bool) ($cancellationQuote['eligible'] ?? false),
                'can_reschedule' => HotelSettings::enabled('hotel.reschedule.enabled') && in_array($booking->status, [HotelBookingStatus::Pending, HotelBookingStatus::Confirmed], true),
                'can_review' => (bool) ($reviewEligibility['can_review'] ?? false),
                'has_review' => (bool) ($reviewEligibility['has_review'] ?? false),
            ],
        ];
    }

    /** @param array<string, mixed> $quote @return array<string, mixed> */
    protected function presentCancellationQuote(array $quote, string $currency): array
    {
        foreach (['cancellation_fee', 'refundable_amount', 'already_refunded', 'remaining_refundable'] as $field) {
            if (array_key_exists($field, $quote)) {
                $quote['display_money'][$field] = MoneyPresenter::present($quote[$field], $currency);
            }
        }

        $quote['policy_summary'] = $this->safePolicy($quote['policy_summary'] ?? []);

        return $quote;
    }

    /** @param array<string, mixed> $quote @return array<string, mixed> */
    protected function presentRescheduleQuote(array $quote, string $currency): array
    {
        $safe = collect($quote)->only([
            'eligible', 'old_dates', 'new_dates', 'old_total', 'new_total', 'difference',
            'additional_payment_due', 'refundable_difference', 'availability', 'quote_fingerprint',
        ])->all();

        foreach (['old_total', 'new_total', 'difference', 'additional_payment_due', 'refundable_difference'] as $field) {
            if (array_key_exists($field, $safe)) {
                $safe['display_money'][$field] = MoneyPresenter::present($safe[$field], $currency);
            }
        }

        return $safe;
    }

    /** @param array<string, mixed>|null $policy @return array<string, mixed> */
    protected function safePolicy(?array $policy): array
    {
        return collect($policy ?? [])->only(['mode', 'summary', 'free_until_hours', 'fee_type', 'fee_value'])->all();
    }

    protected function propertyImage(HotelBooking $booking): ?string
    {
        $image = $booking->property?->images?->firstWhere('is_primary', true) ?? $booking->property?->images?->first();

        return $image?->url();
    }
}
