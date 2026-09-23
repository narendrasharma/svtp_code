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
use App\Services\MoneyPresenter;
use App\Services\TourAddonService;
use App\Services\TourAvailabilityService;
use App\Services\TourBookingPricingService;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(Request $request, TourPackage $package): Response
    {
        abort_unless($package->is_active && $package->moderation_status?->value === 'approved', 404);

        $locale = Localization::currentLocale();
        $package->load([
            'city:id,name,slug',
            'category:id,name,slug,is_active',
            'destinations' => fn ($query) => $query
                ->where('destinations.is_active', true)
                ->orderBy('destinations.sort_order')
                ->orderBy('destinations.name'),
            'translations' => fn ($query) => $query->whereIn('locale', array_values(array_unique([
                $locale,
                Localization::defaultLocale(),
            ]))),
        ]);

        $user = auth()->user();
        $travelDate = $request->query('travel_date');
        $travelDate = is_string($travelDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $travelDate) ? $travelDate : null;
        $adults = max(1, min(30, (int) $request->query('adults', 1)));
        $children = max(0, min(30, (int) $request->query('children', 0)));
        $addonRows = $package->activeAddons()->orderBy('sort_order')->orderBy('id')->get([
            'id', 'name', 'description', 'pricing_type', 'price', 'is_required', 'max_quantity',
        ]);
        $initialResolved = $this->addons->resolve($package, [], $adults, $children);
        $initialQuote = $this->pricing->quoteDetailed($package, $adults, $children, $initialResolved['lines']);
        $category = $package->getRelation('category');

        return Inertia::render('Booking/Create', [
            'package' => [
                'id' => $package->id,
                'title' => $package->translated('title', $locale) ?: $package->title,
                'slug' => $package->slug,
                'cover_image' => $package->cover_image,
                'destination' => $package->destinations->first()?->name,
                'city' => $package->city?->name,
                'category' => is_object($category) && $category->is_active !== false ? $category->name : null,
                'duration_days' => $package->duration_days,
                'duration_nights' => $package->duration_nights,
            ],
            'addons' => $addonRows->map(fn ($addon): array => [
                'id' => (int) $addon->id,
                'name' => $addon->name,
                'description' => $addon->description,
                'pricing_type' => $addon->pricing_type,
                'price_money' => MoneyPresenter::present($addon->price, $initialQuote['currency']),
                'is_required' => (bool) $addon->is_required,
                'max_quantity' => $addon->max_quantity,
            ])->values()->all(),
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
            'selection' => [
                'travel_date' => $travelDate,
                'adults' => $adults,
                'children' => $children,
            ],
            'quote' => $this->quotePresentation($initialQuote),
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
            ...$this->quotePresentation($quote),
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
        abort_unless($booking->isTour(), 404);

        $booking->load([
            'package:id,title,slug,cover_image,duration_days,duration_nights,city_id',
            'package.city:id,name,slug',
            'package.destinations:id,name,slug',
            'package.translations' => fn ($query) => $query->whereIn('locale', array_values(array_unique([
                Localization::currentLocale(),
                Localization::defaultLocale(),
            ]))),
            'bookingAddons',
        ]);
        $currency = (string) $booking->currency;
        $localizedTitle = $booking->package
            ? ($booking->package->translated('title', Localization::currentLocale()) ?: $booking->package->title)
            : null;

        return Inertia::render('Booking/Confirmation', [
            'booking' => [
                'booking_reference_id' => $booking->booking_reference_id,
                'package' => $booking->package ? [
                    'title' => $localizedTitle,
                    'slug' => $booking->package->slug,
                    'cover_image' => $booking->package->cover_image,
                    'duration_days' => $booking->package->duration_days,
                    'duration_nights' => $booking->package->duration_nights,
                    'city' => $booking->package->city?->name,
                    'destination' => $booking->package->destinations->first()?->name,
                ] : null,
                'travel_date' => $booking->travel_date?->toDateString(),
                'total_adults' => $booking->total_adults,
                'total_children' => $booking->total_children,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'country' => $booking->country,
                'pickup_address' => $booking->pickup_address,
                'special_requests' => $booking->special_requests,
                'currency' => $currency,
                'base_price' => $booking->base_price,
                'addons_total' => $booking->addons_total,
                'addons' => $booking->bookingAddons->map(fn ($line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'total_amount' => $line->total_amount,
                    'total_money' => MoneyPresenter::present($line->total_amount, $currency),
                ])->all(),
                'coupon_code' => $booking->coupon_code,
                'subtotal' => $booking->subtotal,
                'discount_amount' => $booking->discount_amount,
                'tax_amount' => $booking->tax_amount,
                'total_amount' => $booking->total_amount,
                'booking_status' => $booking->booking_status->value,
                'payment_status' => $booking->payment_status->value,
                'created_at' => $booking->created_at?->toDateTimeString(),
                'display_money' => [
                    'base_price' => MoneyPresenter::present($booking->base_price, $currency),
                    'addons_total' => MoneyPresenter::present($booking->addons_total, $currency),
                    'subtotal' => MoneyPresenter::present($booking->subtotal, $currency),
                    'discount_amount' => MoneyPresenter::present($booking->discount_amount, $currency),
                    'tax_amount' => MoneyPresenter::present($booking->tax_amount, $currency),
                    'total_amount' => MoneyPresenter::present($booking->total_amount, $currency),
                ],
            ],
            'payUrl' => URL::signedRoute('booking.pay', $booking),
        ]);
    }

    /**
     * Keep all financial line values server-derived before they reach Vue.
     *
     * @param  array<string, mixed>  $quote
     * @return array{display_money: array<string, array<string, mixed>>, display_breakdown: array<string, mixed>}
     */
    private function quotePresentation(array $quote): array
    {
        $currency = (string) $quote['currency'];
        $adultTotal = round((float) $quote['base_price'] * (int) $quote['total_adults'], 2);
        $childTotal = round((float) $quote['child_unit_price'] * (int) $quote['total_children'], 2);

        return [
            'display_money' => [
                'base_price' => MoneyPresenter::present($quote['base_price'], $currency),
                'child_unit_price' => MoneyPresenter::present($quote['child_unit_price'], $currency),
                'subtotal' => MoneyPresenter::present($quote['subtotal'], $currency),
                'discount_amount' => MoneyPresenter::present($quote['discount_amount'], $currency),
                'tax_amount' => MoneyPresenter::present($quote['tax_amount'], $currency),
                'total_amount' => MoneyPresenter::present($quote['total_amount'], $currency),
            ],
            'display_breakdown' => [
                'adults' => [
                    'quantity' => (int) $quote['total_adults'],
                    'unit_money' => MoneyPresenter::present($quote['base_price'], $currency),
                    'total_money' => MoneyPresenter::present($adultTotal, $currency),
                ],
                'children' => [
                    'quantity' => (int) $quote['total_children'],
                    'unit_money' => MoneyPresenter::present($quote['child_unit_price'], $currency),
                    'total_money' => MoneyPresenter::present($childTotal, $currency),
                ],
                'addons' => collect($quote['addons'] ?? [])->map(fn (array $addon): array => [
                    'name' => $addon['name'],
                    'quantity' => (int) $addon['quantity'],
                    'total_money' => MoneyPresenter::present($addon['total'], $currency),
                ])->values()->all(),
                'subtotal_money' => MoneyPresenter::present($quote['subtotal'], $currency),
                'discount_money' => MoneyPresenter::present($quote['discount_amount'], $currency),
                'tax_money' => MoneyPresenter::present($quote['tax_amount'], $currency),
                'total_money' => MoneyPresenter::present($quote['total_amount'], $currency),
            ],
        ];
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
