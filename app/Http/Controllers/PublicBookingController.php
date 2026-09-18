<?php

namespace App\Http\Controllers;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\EstimateBookingPriceRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Services\BookingService;
use App\Services\CouponService;
use App\Services\TourAddonService;
use App\Services\TourAvailabilityService;
use App\Services\TourBookingPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public (guest-capable) tour booking flow.
 *
 * Thin HTTP layer over BookingService: validation here, money and booking
 * rules in services. Works for guests (user_id stays null) and signed-in
 * customers (user_id links automatically, never from the request).
 */
class PublicBookingController extends Controller
{
    public function __construct(
        protected BookingService $bookings,
        protected TourBookingPricingService $pricing,
        protected TourAddonService $addons,
        protected CouponService $coupons,
        protected TourAvailabilityService $availability,
    ) {}

    public function create(TourPackage $package): Response
    {
        abort_unless($package->is_active && $package->moderation_status?->value === 'approved', 404);

        $user = auth()->user();

        return Inertia::render('Booking/Create', [
            'package' => [
                'id' => $package->id,
                'title' => $package->title,
                'slug' => $package->slug,
                'price' => $package->price,
                'discounted_price' => $package->discounted_price,
                'effective_price' => $package->effective_price,
                'duration_days' => $package->duration_days,
                'duration_nights' => $package->duration_nights,
            ],
            'addons' => $package->activeAddons()->orderBy('sort_order')->orderBy('id')->get([
                'id', 'name', 'description', 'pricing_type', 'price', 'is_required', 'max_quantity',
            ]),
            'availability' => [
                'booking_enabled' => (bool) ($package->booking_enabled ?? true),
                'available_weekdays' => $package->available_weekdays,
                'min_advance_days' => (int) ($package->min_advance_days ?? 0),
                'max_advance_days' => $package->max_advance_days,
                'blackout_dates' => $package->blackoutDates()->whereDate('date', '>=', now()->toDateString())->orderBy('date')->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all(),
            ],
            'customer' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ] : null,
        ]);
    }

    public function estimate(EstimateBookingPriceRequest $request): JsonResponse
    {
        $package = TourPackage::findOrFail($request->integer('package_id'));
        $adults = $request->integer('total_adults');
        $children = $request->integer('total_children', 0);
        $extras = $request->extrasData();

        $resolved = $this->addons->resolve($package, $extras['addons'], $adults, $children);
        $subtotal = round($this->pricing->quote($package, $adults, $children)['subtotal'] + $resolved['total'], 2);

        $coupon = null;
        $discount = 0.0;

        if ($extras['coupon_code'] !== null) {
            try {
                $preview = $this->coupons->preview(
                    $extras['coupon_code'],
                    $package,
                    $subtotal,
                    $request->user(),
                    $request->input('customer_email')
                );
                $coupon = $preview['coupon'];
                $discount = $preview['discount_amount'];
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => 'Promo code is not valid for this booking.',
                    'errors' => $e->errors(),
                ], 422);
            }
        }

        $quote = $this->pricing->quoteDetailed($package, $adults, $children, $resolved['lines'], $discount);

        $availability = null;

        if ($request->filled('travel_date')) {
            $availability = $this->availability->check($package, $request->string('travel_date')->toString());
        }

        return response()->json([
            ...$quote,
            'coupon' => $coupon ? ['code' => $coupon->code, 'discount_type' => $coupon->discount_type] : null,
            'availability' => $availability,
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $package = TourPackage::findOrFail($request->integer('package_id'));

        $booking = $this->bookings->createTourBooking(
            $package,
            [
                'adults' => $request->integer('total_adults'),
                'children' => $request->integer('total_children', 0),
                'travel_date' => $request->string('travel_date')->toString(),
            ],
            $request->customerData(),
            $request->user(),
            BookingSource::Website,
            $request->user(),
            null,
            null,
            $request->extrasData(),
        );

        return redirect()
            ->to(URL::signedRoute('booking.confirmation', $booking))
            ->with('flash', 'Booking created! Please review your confirmation.');
    }

    /**
     * Signed confirmation link: safe for guests (non-guessable HMAC, not
     * derived from the reference) and reusable by owners via history links.
     */
    public function confirmation(Booking $booking): Response
    {
        $booking->load(['package:id,title,slug', 'bookingAddons']);

        return Inertia::render('Booking/Confirmation', [
            'booking' => [
                'booking_reference_id' => $booking->booking_reference_id,
                'package' => $booking->package ? ['title' => $booking->package->title, 'slug' => $booking->package->slug] : null,
                'travel_date' => $booking->travel_date?->toDateString(),
                'total_adults' => $booking->total_adults,
                'total_children' => $booking->total_children,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'country' => $booking->country,
                'currency' => $booking->currency,
                'base_price' => $booking->base_price,
                'addons_total' => $booking->addons_total,
                'addons' => $booking->bookingAddons->map(fn ($line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'total_amount' => $line->total_amount,
                ])->all(),
                'coupon_code' => $booking->coupon_code,
                'subtotal' => $booking->subtotal,
                'discount_amount' => $booking->discount_amount,
                'tax_amount' => $booking->tax_amount,
                'total_amount' => $booking->total_amount,
                'booking_status' => $booking->booking_status->value,
                'payment_status' => $booking->payment_status->value,
                'created_at' => $booking->created_at?->toDateTimeString(),
            ],
            'payUrl' => URL::signedRoute('booking.pay', $booking),
        ]);
    }

    /**
     * Handoff into the (stub) payment page. Amount always comes from the
     * stored snapshot — nothing here accepts money from the browser.
     */
    public function pay(Booking $booking)
    {
        if ($booking->booking_status !== BookingStatus::Pending || $booking->payment_status !== PaymentStatus::Unpaid) {
            return redirect()->to(URL::signedRoute('booking.confirmation', $booking));
        }

        return Inertia::render('Booking/Payment', [
            'booking' => $booking->load('package'),
            'order' => null,
        ]);
    }
}
