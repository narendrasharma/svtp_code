<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualBookingRequest;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use App\Services\BookingPaymentService;
use App\Services\BookingRefundService;
use App\Services\BookingService;
use App\Services\CancellationService;
use App\Services\VendorLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BookingManagerController extends Controller
{
    public function __construct(protected BookingService $bookings, protected CancellationService $cancellations, protected VendorLedgerService $ledger, protected BookingRefundService $refunds, protected BookingPaymentService $payments) {}

    public function index(Request $request): Response
    {
        $bookings = Booking::with('package:id,title', 'user:id,name', 'vendorProfile:id,business_name')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('booking_reference_id', 'like', $term)
                        ->orWhere('customer_name', 'like', $term)
                        ->orWhere('customer_email', 'like', $term)
                        ->orWhere('customer_phone', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('booking_status', $request->string('status')->toString()))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('package_id'), fn ($query) => $query->where('package_id', $request->integer('package_id')))
            ->when($request->string('vendor')->toString() === 'admin', fn ($query) => $query->whereNull('vendor_profile_id'))
            ->when($request->integer('vendor'), fn ($query) => $query->where('vendor_profile_id', $request->integer('vendor')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('travel_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('travel_date', '<=', $request->date('date_to')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Bookings/Index', [
            'adminBreadcrumbs' => $this->tourBreadcrumbs('Tour Bookings'),
            'bookings' => $bookings,
            'filters' => $request->only(['search', 'status', 'payment_status', 'package_id', 'vendor', 'date_from', 'date_to']),
            'packages' => TourPackage::orderBy('title')->get(['id', 'title']),
            'vendors' => VendorProfile::whereHas('bookings')->orderBy('business_name')->get(['id', 'business_name']),
            'statuses' => collect(BookingStatus::cases())->map(fn (BookingStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'paymentStatuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function show(Booking $booking): Response
    {
        $booking->load('package', 'user:id,name,email,phone', 'vendorProfile:id,business_name,email,phone', 'statusHistories.changer:id,name', 'cancellationRequests.requester:id,name', 'cancellationRequests.reviewer:id,name', 'refunds', 'bookingAddons', 'payments.receiver:id,name', 'payments.creator:id,name', 'reschedules.requester:id,name', 'reschedules.approver:id,name', 'notes.author:id,name', 'quotation:id,reference,revision_number,total_amount');

        return Inertia::render('Admin/Bookings/Show', [
            'adminBreadcrumbs' => $this->tourBreadcrumbs('Tour Booking #'.($booking->booking_reference_id ?? $booking->id)),
            'booking' => $booking,
            // Phase 7: ledger state only (Credited / Reversed / Pending
            // payment / Not eligible) — the full ledger lives in Finance.
            'ledgerState' => $this->ledger->ledgerStateForBooking($booking),
            // Phase 8: manual accounting refund context. Recording is an
            // accounting entry — no money moves electronically.
            'refundSummary' => [
                'refunded_total' => $this->refunds->processedRefundTotal($booking),
                'refundable_remaining' => $this->refunds->refundableRemaining($booking),
                'vendor_reversed_total' => $this->refunds->reversedVendorTotal($booking),
                'vendor_earning_remaining' => $this->refunds->remainingVendorEarning($booking),
                'refund_form_key' => (string) Str::uuid(),
            ],
            // Phase 11.5B: derived money position + full operational
            // timeline (histories, payments, reschedules, notes).
            'paymentSummary' => $this->payments->summary($booking),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()]),
            'statuses' => collect(BookingStatus::cases())->map(fn (BookingStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'paymentStatuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'permissions' => [
                'record_payment' => $this->canRecordPayment(),
                'reschedule' => request()->user()->can('bookings.reschedule'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Bookings/Form', [
            'adminBreadcrumbs' => $this->tourBreadcrumbs('New Tour Booking'),
            'packages' => TourPackage::active()->orderBy('title')->get(['id', 'title', 'price', 'discounted_price']),
            'statuses' => collect(BookingStatus::cases())->map(fn (BookingStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'paymentStatuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    /**
     * Reservation Desk: one structured screen for phone / walk-in /
     * WhatsApp bookings — customer search or quick-create, tour, dates,
     * pricing preview and an optional first collection.
     */
    public function desk(): Response
    {
        return Inertia::render('Admin/Bookings/Desk', [
            'adminBreadcrumbs' => $this->tourBreadcrumbs('New Tour Booking'),
            'packages' => TourPackage::active()->orderBy('title')->get(['id', 'title', 'price', 'discounted_price']),
            'sources' => collect(BookingSource::staffCreatable())->map(fn (string $value): array => [
                'value' => $value, 'label' => BookingSource::from($value)->label(),
            ]),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()]),
            'statuses' => collect(BookingStatus::cases())->map(fn (BookingStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function store(StoreManualBookingRequest $request): RedirectResponse
    {
        $package = TourPackage::findOrFail($request->integer('package_id'));

        $user = $request->filled('user_id') ? User::findOrFail($request->integer('user_id')) : null;

        if ($user && ! $user->isCustomer()) {
            abort(422, 'Bookings can only be linked to customer accounts.');
        }

        $source = $request->filled('source')
            ? BookingSource::from($request->string('source')->toString())
            : BookingSource::Admin;

        $booking = $this->bookings->createTourBooking(
            $package,
            [
                'adults' => $request->integer('total_adults'),
                'children' => $request->integer('total_children', 0),
                'travel_date' => $request->string('travel_date')->toString(),
            ],
            $request->customerData(),
            $user,
            $source,
            $request->user(),
            $request->filled('booking_status') ? BookingStatus::from($request->string('booking_status')->toString()) : null,
            $request->filled('payment_status') ? PaymentStatus::from($request->string('payment_status')->toString()) : null,
            $request->extrasData(),
        );

        // Optional first collection on the desk (same request, explicit).
        if ($request->filled('initial_payment_amount') && (float) $request->input('initial_payment_amount') > 0) {
            abort_unless($this->canRecordPayment(), 403, 'You may create the booking but not collect payments.');

            $this->payments->recordPayment(
                $booking,
                (float) $request->input('initial_payment_amount'),
                PaymentMethod::from($request->string('initial_payment_method', PaymentMethod::Cash->value)->toString()),
                $request->user(),
                $request->string('initial_payment_note')->toString() ?: null,
            );

            return redirect()->route('admin.bookings.show', $booking)->with('flash', 'Booking created with first payment recorded.');
        }

        if ($booking->user) {
            $booking->user->notify(new CrmNotification('offline_booking_created', [
                'booking_id' => $booking->id,
                'reference' => $booking->booking_reference_id,
                'total' => (string) $booking->total_amount,
            ]));
        }

        return redirect()->route('admin.bookings.show', $booking)->with('flash', 'Manual booking created.');
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): RedirectResponse
    {
        if ($request->filled('booking_status')) {
            $this->bookings->changeStatus(
                $booking,
                BookingStatus::from($request->string('booking_status')->toString()),
                $request->user(),
                $request->input('note')
            );
        }

        if ($request->filled('payment_status')) {
            $this->bookings->markPayment(
                $booking,
                PaymentStatus::from($request->string('payment_status')->toString()),
                $request->user(),
                $request->input('note')
            );
        }

        return back()->with('flash', 'Booking updated.');
    }

    public function approveCancellation(Request $request, Booking $booking, BookingCancellationRequest $cancellation): RedirectResponse
    {
        abort_unless((int) $cancellation->booking_id === (int) $booking->id, 404);
        $request->validate(['review_note' => ['nullable', 'string', 'max:255']]);

        $this->cancellations->approve($cancellation, $request->user(), $request->input('review_note'));

        return back()->with('flash', 'Cancellation approved; the booking is now cancelled.');
    }

    public function rejectCancellation(Request $request, Booking $booking, BookingCancellationRequest $cancellation): RedirectResponse
    {
        abort_unless((int) $cancellation->booking_id === (int) $booking->id, 404);
        $request->validate(['review_note' => ['nullable', 'string', 'max:255']]);

        $this->cancellations->reject($cancellation, $request->user(), $request->input('review_note'));

        return back()->with('flash', 'Cancellation request rejected.');
    }

    protected function canRecordPayment(): bool
    {
        return (bool) request()->user()?->can('payments.record');
    }

    /**
     * @return array<int, array{label: string, href?: string}>
     */
    protected function tourBreadcrumbs(string $currentLabel): array
    {
        return [
            ['label' => 'Dashboard', 'href' => '/admin/dashboard'],
            ['label' => 'Tour Bookings', 'href' => '/admin/tour/bookings'],
            ['label' => $currentLabel],
        ];
    }
}
