<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\BookingRefundService;
use App\Services\BookingService;
use App\Services\VendorLedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor bookings — read-only except own-product offline sales.
 *
 * Vendors see only bookings historically assigned to their own vendor
 * profile. There are deliberately no status/payment/split mutations here:
 * payment and cancellation authority stays with admin (customer requests
 * flow through CancellationService). Financial snapshots are immutable.
 */
class BookingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected VendorLedgerService $ledger, protected BookingRefundService $refunds, protected BookingService $bookings) {}

    /**
     * Money cards count PAID, non-cancelled bookings only — unpaid and
     * cancelled rows never inflate recorded earnings. No wallet/balance
     * language: payouts do not exist yet (Phase 7 ledger).
     */
    public function index(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        $query = Booking::assignedToVendor($profile->id)->with('package:id,title,slug');

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(function ($query) use ($term): void {
                $query->where('booking_reference_id', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term);
            });
        }

        if ($request->filled('booking_status')) {
            $query->where('booking_status', $request->string('booking_status')->toString());
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status')->toString());
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->integer('package_id'));
        }

        $query->when(
            $request->string('sort')->toString() === 'oldest',
            fn ($query) => $query->oldest(),
            fn ($query) => $query->latest(),
        );

        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $bookings = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Vendor/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $request->only(['search', 'booking_status', 'payment_status', 'package_id', 'sort', 'per_page']),
            'statuses' => collect(BookingStatus::cases())->map(fn (BookingStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'paymentStatuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'tours' => TourPackage::where('vendor_profile_id', $profile->id)->orderBy('title')->get(['id', 'title']),
            'summary' => $this->summary($profile->id),
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        $this->authorize('view', $booking);

        $booking->load([
            'package:id,title,slug,duration_days,duration_nights',
            'user:id,name,email,phone',
            'bookingAddons',
            'cancellationRequests' => fn ($query) => $query->latest()->limit(1),
        ]);

        $latestCancellation = $booking->cancellationRequests->first();

        // Explicit shaping: only fulfillment data, never the full user
        // model, tokens, payment secrets or admin-only notes.
        return Inertia::render('Vendor/Bookings/Show', [
            'booking' => [
                'id' => $booking->id,
                'booking_reference_id' => $booking->booking_reference_id,
                'tour' => $booking->package ? [
                    'id' => $booking->package->id,
                    'title' => $booking->package->title,
                    'slug' => $booking->package->slug,
                    'duration_days' => $booking->package->duration_days,
                    'duration_nights' => $booking->package->duration_nights,
                ] : null,
                'travel_date' => $booking->travel_date?->toDateString(),
                'total_adults' => $booking->total_adults,
                'total_children' => $booking->total_children,
                'pickup_address' => $booking->pickup_address,
                'country' => $booking->country,
                'special_requests' => $booking->special_requests,
                'customer' => [
                    'name' => $booking->customer_name ?? $booking->user?->name,
                    'email' => $booking->customer_email ?? $booking->user?->email,
                    'phone' => $booking->customer_phone ?? $booking->user?->phone,
                ],
                'booking_status' => $booking->booking_status->value,
                'payment_status' => $booking->payment_status->value,
                'currency' => $booking->currency,
                'pricing' => [
                    'base_price' => $booking->base_price,
                    'addons_total' => $booking->addons_total,
                    'subtotal' => $booking->subtotal,
                    'discount_amount' => $booking->discount_amount,
                    'coupon_code' => $booking->coupon_code,
                    'tax_amount' => $booking->tax_amount,
                    'total_amount' => $booking->total_amount,
                    'gross_amount' => $booking->gross_amount,
                    'platform_commission_percentage' => $booking->platform_commission_percentage,
                    'platform_commission_amount' => $booking->platform_commission_amount,
                    'vendor_earning_amount' => $booking->vendor_earning_amount,
                ],
                'addons' => $booking->bookingAddons->map(fn ($line): array => [
                    'name' => $line->name,
                    'pricing_type' => $line->pricing_type,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total_amount' => $line->total_amount,
                ])->all(),
                'payment_gateway' => $booking->payment_gateway,
                'source' => $booking->source->value,
                'created_at' => $booking->created_at?->toDateTimeString(),
                // Phase 7: per-booking financial state only (Credited /
                // Reversed / Pending payment). Balances live in Finance.
                'ledger_state' => $this->ledger->ledgerStateForBooking($booking),
                // Phase 8: refund impact (customer totals + earning effect).
                // No internal admin notes or actor details.
                'refund_impact' => [
                    'refunded_total' => $this->refunds->processedRefundTotal($booking),
                    'earning_reversed' => $this->refunds->reversedVendorTotal($booking),
                    'earning_remaining' => $this->refunds->remainingVendorEarning($booking),
                ],
                'cancellation' => $latestCancellation ? [
                    'status' => $latestCancellation->status->value,
                    'reason' => $latestCancellation->reason,
                    'requested_at' => $latestCancellation->created_at?->toDateTimeString(),
                ] : null,
            ],
        ]);
    }

    /**
     * Vendor offline booking: a vendor may sell its OWN tours over the
     * counter (phone/walk-in guests). Cross-vendor booking is refused —
     * the package must belong to the vendor's own profile. Money
     * collection stays with admin (unpaid until recorded there).
     */
    public function create(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        return Inertia::render('Vendor/Bookings/Create', [
            'tours' => TourPackage::where('vendor_profile_id', $profile->id)
                ->active()->orderBy('title')->get(['id', 'title', 'price', 'discounted_price']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:tour_packages,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'travel_date' => ['required', 'date'],
            'total_adults' => ['required', 'integer', 'min:1', 'max:100'],
            'total_children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required_with:addons', 'integer', 'exists:tour_addons,id'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $package = TourPackage::findOrFail($validated['package_id']);

        // Own-product gate: never another vendor's (or admin's) tour.
        abort_unless((int) $package->vendor_profile_id === (int) $profile->id, 403, 'You can only sell your own tours.');

        $user = ! empty($validated['user_id']) ? User::findOrFail($validated['user_id']) : null;

        if ($user && ! $user->isCustomer()) {
            abort(422, 'Bookings can only be linked to customer accounts.');
        }

        $booking = $this->bookings->createTourBooking(
            $package,
            [
                'adults' => $validated['total_adults'],
                'children' => $validated['total_children'] ?? 0,
                'travel_date' => $validated['travel_date'],
            ],
            [
                'name' => $validated['customer_name'],
                'email' => $validated['customer_email'] ?? null,
                'phone' => $validated['customer_phone'],
                'pickup_address' => $validated['pickup_address'] ?? null,
                'special_requests' => $validated['special_requests'] ?? null,
            ],
            $user,
            BookingSource::Vendor,
            $request->user(),
            null,
            null,
            ['addons' => $validated['addons'] ?? []],
        );

        return redirect()->route('vendor.bookings.show', $booking)->with('flash', "Offline booking {$booking->booking_reference_id} created (unpaid — payment is recorded by admin).");
    }

    /**
     * @return array{assigned_bookings: int, paid_gross: string, platform_commission: string, recorded_vendor_earnings: string}
     */
    protected function summary(int $vendorProfileId): array
    {
        $assigned = Booking::assignedToVendor($vendorProfileId);

        $paid = (clone $assigned)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('booking_status', '!=', BookingStatus::Cancelled->value);

        return [
            'assigned_bookings' => (clone $assigned)->count(),
            'paid_gross' => number_format((float) (clone $paid)->sum('gross_amount'), 2, '.', ''),
            'platform_commission' => number_format((float) (clone $paid)->sum('platform_commission_amount'), 2, '.', ''),
            'recorded_vendor_earnings' => number_format((float) (clone $paid)->sum('vendor_earning_amount'), 2, '.', ''),
        ];
    }
}
