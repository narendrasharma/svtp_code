<?php

namespace App\Http\Controllers\Account;

use App\Enums\BookingStatus;
use App\Enums\CancellationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreCancellationRequest;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\TourPackage;
use App\Services\BookingPaymentService;
use App\Services\CancellationService;
use App\Services\MoneyPresenter;
use App\Services\ReviewService;
use App\Support\Localization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected CancellationService $cancellations, protected ReviewService $reviews) {}

    public function index(Request $request): Response
    {
        $bookings = $request->user()->bookings()
            ->where('product_type', 'tour')
            ->with([
                'package:id,title,slug,cover_image,duration_days,duration_nights,city_id',
                'package.city:id,name,slug',
                'package.destinations:id,name,slug',
                'package.translations' => fn ($query) => $query->whereIn('locale', array_values(array_unique([
                    Localization::currentLocale(),
                    Localization::defaultLocale(),
                ]))),
            ])
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
            ->withQueryString()
            ->through(fn (Booking $booking): array => $this->card($booking));

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

        abort_unless($booking->product_type === 'tour', 404);

        // Customer-visible shapes only: internal staff notes and internal
        // timeline entries never leave the admin area.
        $booking->load([
            'package:id,title,slug,cover_image,duration_days,duration_nights,city_id',
            'package.city:id,name,slug',
            'package.destinations:id,name,slug',
            'package.translations' => fn ($query) => $query->whereIn('locale', array_values(array_unique([
                Localization::currentLocale(),
                Localization::defaultLocale(),
            ]))),
            'statusHistories' => fn ($q) => $q->where('is_internal', false),
            'cancellationRequests',
            'refunds',
            'bookingAddons',
            'payments',
            'review',
        ]);

        $pendingCancellation = $booking->cancellationRequests
            ->first(fn ($cancellation): bool => $cancellation->status->value === CancellationStatus::Pending->value);
        $reviewEligibility = $this->reviews->bookingEligibility($request->user(), $booking);

        return Inertia::render('Account/Bookings/Show', [
            'booking' => $this->detail($booking),
            'pendingCancellation' => $pendingCancellation ? $this->cancellation($pendingCancellation) : null,
            'refunds' => $booking->refunds->map(fn ($refund): array => [
                'id' => $refund->id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'status' => $refund->status instanceof \BackedEnum ? $refund->status->value : $refund->status,
                'reason' => $refund->reason,
                'processed_at' => $refund->processed_at?->toDateTimeString(),
                'money' => MoneyPresenter::present($refund->amount, $refund->currency),
            ])->values()->all(),
            'paymentSummary' => $this->paymentSummary($booking),
            'reviewEligibility' => $reviewEligibility,
            'review' => $booking->review ? [
                'rating' => $booking->review->rating,
                'comment' => $booking->review->comment,
                'status' => $booking->review->is_approved ? 'approved' : 'pending',
                'created_at' => $booking->review->created_at?->toDateTimeString(),
            ] : null,
            'actions' => [
                'can_cancel' => Gate::forUser($request->user())->allows('requestCancellation', $booking)
                    && in_array($booking->booking_status, [BookingStatus::Pending, BookingStatus::Confirmed], true)
                    && $pendingCancellation === null,
                'can_review' => (bool) $reviewEligibility['can_review'],
            ],
        ]);
    }

    public function requestCancellation(StoreCancellationRequest $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->isTour(), 404);

        $this->authorize('requestCancellation', $booking);

        $this->cancellations->request($booking, $request->user(), $request->input('reason'));

        return back()->with('flash', 'Cancellation request sent. Our team will review it shortly.');
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Booking $booking): array
    {
        $package = $booking->package;
        $money = MoneyPresenter::present($booking->total_amount, (string) $booking->currency);

        return [
            'id' => $booking->id,
            'booking_reference_id' => $booking->booking_reference_id,
            'package' => $this->package($package),
            'travel_date' => $booking->travel_date?->toDateString(),
            'total_adults' => (int) $booking->total_adults,
            'total_children' => (int) $booking->total_children,
            'total_money' => $money,
            'booking_status' => $booking->booking_status->value,
            'booking_status_label' => $booking->booking_status->label(),
            'payment_status' => $booking->payment_status->value,
            'payment_status_label' => $booking->payment_status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Booking $booking): array
    {
        $currency = (string) $booking->currency;

        return [
            ...$this->card($booking),
            'customer' => [
                'name' => $booking->customer_name,
                'phone' => $booking->customer_phone,
                'email' => $booking->customer_email,
                'country' => $booking->country,
                'pickup_address' => $booking->pickup_address,
                'special_requests' => $booking->special_requests,
            ],
            'pricing' => [
                'currency' => $currency,
                'base_price' => $booking->base_price,
                'subtotal' => $booking->subtotal,
                'discount_amount' => $booking->discount_amount,
                'tax_amount' => $booking->tax_amount,
                'addons_total' => $booking->addons_total,
                'total_amount' => $booking->total_amount,
                'base_money' => MoneyPresenter::present($booking->base_price, $currency),
                'subtotal_money' => MoneyPresenter::present($booking->subtotal, $currency),
                'discount_money' => MoneyPresenter::present($booking->discount_amount, $currency),
                'tax_money' => MoneyPresenter::present($booking->tax_amount, $currency),
                'addons_money' => MoneyPresenter::present($booking->addons_total, $currency),
                'total_money' => MoneyPresenter::present($booking->total_amount, $currency),
            ],
            'coupon' => $booking->coupon_code ? [
                'code' => $booking->coupon_code,
                'discount_type' => $booking->coupon_discount_type,
                'discount_value' => $booking->coupon_discount_value,
            ] : null,
            'addons' => $booking->bookingAddons->map(fn ($line): array => [
                'name' => $line->name,
                'pricing_type' => $line->pricing_type,
                'quantity' => (int) $line->quantity,
                'unit_money' => MoneyPresenter::present($line->unit_price, $currency),
                'total_money' => MoneyPresenter::present($line->total_amount, $currency),
            ])->values()->all(),
            'timeline' => $booking->statusHistories->map(fn ($entry): array => [
                'id' => $entry->id,
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'payment_from' => $entry->payment_from,
                'payment_to' => $entry->payment_to,
                'created_at' => $entry->created_at?->toDateTimeString(),
            ])->values()->all(),
            'cancellations' => $booking->cancellationRequests->map(fn ($request): array => $this->cancellation($request))->values()->all(),
            'payments' => $booking->payments->map(fn ($payment): array => [
                'reference' => $payment->reference,
                'amount_money' => MoneyPresenter::present($payment->amount, $payment->currency ?: $currency),
                'payment_method' => $payment->payment_method,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
            ])->values()->all(),
            'travel_date' => $booking->travel_date?->toDateString(),
            'created_at' => $booking->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function package(?TourPackage $package): ?array
    {
        if ($package === null) {
            return null;
        }

        return [
            'title' => $package->translated('title', Localization::currentLocale()),
            'slug' => $package->slug,
            'cover_image' => $package->cover_image,
            'duration_days' => $package->duration_days,
            'duration_nights' => $package->duration_nights,
            'city' => $package->city?->name,
            'destination' => $package->destinations->first()?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cancellation(BookingCancellationRequest $cancellation): array
    {
        return [
            'id' => $cancellation->id,
            'status' => $cancellation->status->value,
            'status_label' => $cancellation->status->label(),
            'reason' => $cancellation->reason,
            'created_at' => $cancellation->created_at?->toDateTimeString(),
            'reviewed_at' => $cancellation->reviewed_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSummary(Booking $booking): array
    {
        $summary = app(BookingPaymentService::class)->summary($booking);
        $currency = (string) ($summary['currency'] ?? $booking->currency);

        foreach (['total', 'paid', 'refunded', 'due'] as $key) {
            $summary[$key.'_money'] = MoneyPresenter::present($summary[$key], $currency);
        }

        return $summary;
    }
}
