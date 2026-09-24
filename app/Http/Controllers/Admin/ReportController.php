<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\HotelBooking;
use App\Models\Lead;
use App\Models\TaxiBooking;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\MarketplaceAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operational reporting foundation (11.5D).
 *
 * Deliberately narrow: booking, payment, lead, vendor-earning and
 * commission reports with server-side filters. No report builder.
 * CSV exports contain operational figures only — never KYC, bank or
 * credential data.
 */
class ReportController extends Controller
{
    public function index(Request $request, MarketplaceAnalyticsService $analytics): Response
    {
        $range = $analytics->resolveRange($request->only(['preset', 'from', 'to']));
        $tab = in_array($request->string('tab')->toString(), ['overview', 'bookings', 'hotels', 'tours', 'taxi', 'customers', 'payments', 'leads', 'vendors', 'commission'], true)
            ? $request->string('tab')->toString()
            : 'overview';

        $pagination = null;
        if (in_array($tab, ['bookings', 'hotels', 'tours', 'taxi', 'customers'], true)) {
            $page = $tab === 'customers'
                ? $this->customerRows($request, $range)
                : $this->systemBookingRows($request, $range, $tab === 'bookings' ? null : $tab);
            $rows = $page['rows'];
            $pagination = $page['pagination'];
        } else {
            $rows = $tab === 'overview' ? [] : $this->rows($tab, $request, $range);
        }

        return Inertia::render('Admin/Reports/Index', [
            'tab' => $tab,
            'range' => $range,
            'filters' => $request->only(['tab', 'preset', 'from', 'to', 'module', 'status', 'payment_status', 'source', 'search', 'vendor_id']),
            'summary' => $analytics->adminSummary($range['from'], $range['to']),
            'rows' => $rows,
            'pagination' => $pagination,
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function export(Request $request, MarketplaceAnalyticsService $analytics): StreamedResponse
    {
        $range = $analytics->resolveRange($request->only(['preset', 'from', 'to']));
        $tab = in_array($request->string('tab')->toString(), ['bookings', 'hotels', 'tours', 'taxi', 'customers', 'payments', 'leads', 'vendors', 'commission'], true)
            ? $request->string('tab')->toString()
            : 'bookings';

        $systemTabs = ['bookings', 'hotels', 'tours', 'taxi'];
        $rows = in_array($tab, $systemTabs, true)
            ? $this->systemBookingRows($request, $range, $tab === 'bookings' ? null : $tab, 2000)['rows']
            : $this->rows($tab, $request, $range, true);

        return response()->streamDownload(function () use ($tab, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $this->columns($tab));

            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }

            fclose($out);
        }, "report-{$tab}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<int, string> */
    protected function columns(string $tab): array
    {
        return match ($tab) {
            'payments' => ['reference', 'booking', 'amount', 'currency', 'method', 'paid_at'],
            'leads' => ['reference', 'name', 'phone', 'status', 'priority', 'service', 'created_at'],
            'vendors' => ['vendor', 'bookings_paid', 'paid_booking_value', 'recorded_earnings'],
            'commission' => ['booking', 'travel_date', 'gross_value', 'platform_commission', 'vendor_earning'],
            'bookings', 'hotels', 'tours', 'taxi' => ['module', 'booking', 'customer', 'status', 'payment_status', 'amount', 'currency', 'created_at'],
            'customers' => ['customer', 'email', 'created_at'],
            default => ['booking', 'customer', 'tour', 'travel_date', 'status', 'payment_status', 'total'],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(string $tab, Request $request, array $range, bool $all = false): array
    {
        $limit = $all ? 2000 : 50;

        return match ($tab) {
            'payments' => $this->paymentRows($request, $range, $limit),
            'leads' => $this->leadRows($request, $range, $limit),
            'vendors' => $this->vendorRows($range),
            'commission' => $this->commissionRows($request, $range, $limit),
            default => $this->bookingRows($request, $range, $limit),
        };
    }

    /** @return array<int, array<string, mixed>> */
    protected function bookingRows(Request $request, array $range, int $limit): array
    {
        return Booking::with(['package:id,title', 'user:id,name'])
            ->when($range['from'], fn ($q) => $q->where('created_at', '>=', $range['from']))
            ->when($range['to'], fn ($q) => $q->where('created_at', '<=', $range['to']))
            ->when($request->filled('status'), fn ($q) => $q->where('booking_status', $request->string('status')->toString()))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')->toString()))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Booking $b): array => [
                'booking' => $b->booking_reference_id,
                'customer' => $b->customer_name ?? $b->user?->name,
                'tour' => $b->package?->title,
                'travel_date' => $b->travel_date?->toDateString(),
                'status' => $b->booking_status->value,
                'payment_status' => $b->payment_status->value,
                'total' => (string) $b->total_amount,
            ])->all();
    }

    /**
     * Cross-module booking table. Each source keeps its own status and
     * currency; the normalized columns only make the report filterable.
     *
     * @return array{rows:array<int,array<string,mixed>>,pagination:array<string,mixed>}
     */
    protected function systemBookingRows(Request $request, array $range, ?string $module = null, int $perPage = 20): array
    {
        $tour = Booking::query()->selectRaw("'tours' as module, booking_reference_id as reference, customer_name as customer, booking_status as status, payment_status, total_amount as amount, currency, vendor_profile_id, created_at");
        $hotel = HotelBooking::query()->selectRaw("'hotels' as module, booking_number as reference, guest_name as customer, status, payment_status, total as amount, currency, vendor_profile_id, created_at");
        $taxi = TaxiBooking::query()->selectRaw("'taxi' as module, reference, customer_name as customer, status, payment_status, total_amount as amount, currency, vendor_profile_id, created_at");

        foreach ([$tour, $hotel, $taxi] as $query) {
            $query
                ->when($range['from'], fn ($q) => $q->where('created_at', '>=', $range['from']))
                ->when($range['to'], fn ($q) => $q->where('created_at', '<=', $range['to']));
        }

        $union = $tour->unionAll($hotel)->unionAll($taxi);
        $query = DB::query()->fromSub($union, 'reporting_bookings')
            ->when($module, fn ($q) => $q->where('module', $module))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $term)->orWhere('customer', 'like', $term));
            })
            ->orderByDesc('created_at');

        $page = $query->paginate($perPage)->withQueryString();
        $pagination = $page->toArray();
        unset($pagination['data']);

        return [
            'rows' => collect($page->items())->map(fn (object $row): array => [
                'module' => ucfirst((string) $row->module),
                'booking' => $row->reference,
                'reference' => $row->reference,
                'customer' => $row->customer,
                'status' => $row->status,
                'payment_status' => $row->payment_status,
                'amount' => (string) $row->amount,
                'currency' => strtoupper((string) $row->currency),
                'created_at' => $row->created_at,
            ])->all(),
            'pagination' => $pagination,
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>,pagination:array<string,mixed>} */
    protected function customerRows(Request $request, array $range): array
    {
        $page = User::query()->where('role', 'customer')
            ->when($range['from'], fn ($q) => $q->where('created_at', '>=', $range['from']))
            ->when($range['to'], fn ($q) => $q->where('created_at', '<=', $range['to']))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest()->paginate(20)->withQueryString();
        $pagination = $page->toArray();
        unset($pagination['data']);

        return [
            'rows' => collect($page->items())->map(fn (User $user): array => [
                'customer' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toDateString(),
            ])->all(),
            'pagination' => $pagination,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function paymentRows(Request $request, array $range, int $limit): array
    {
        return BookingPayment::with('booking:id,booking_reference_id')
            ->when($range['from'], fn ($q) => $q->where('created_at', '>=', $range['from']))
            ->when($range['to'], fn ($q) => $q->where('created_at', '<=', $range['to']))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (BookingPayment $p): array => [
                'reference' => $p->reference,
                'booking' => $p->booking?->booking_reference_id,
                'amount' => (string) $p->amount,
                'currency' => $p->currency,
                'method' => $p->payment_method,
                'paid_at' => $p->paid_at?->toDateTimeString(),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function leadRows(Request $request, array $range, int $limit): array
    {
        return Lead::query()
            ->when($range['from'], fn ($q) => $q->where('created_at', '>=', $range['from']))
            ->when($range['to'], fn ($q) => $q->where('created_at', '<=', $range['to']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()
            ->limit($limit)
            ->get(['reference', 'name', 'phone', 'status', 'priority', 'service_type', 'created_at'])
            ->map(fn (Lead $l): array => [
                'reference' => $l->reference,
                'name' => $l->name,
                'phone' => $l->phone,
                'status' => $l->status,
                'priority' => $l->priority,
                'service' => $l->service_type,
                'created_at' => $l->created_at?->toDateString(),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function vendorRows(array $range): array
    {
        return VendorProfile::orderBy('business_name')
            ->limit(200)
            ->get()
            ->map(function (VendorProfile $profile) use ($range): array {
                $summary = app(MarketplaceAnalyticsService::class)->vendorSummary($profile, $range['from'], $range['to']);

                return [
                    'vendor' => $profile->business_name,
                    'bookings_paid' => $summary['bookings_paid'],
                    'paid_booking_value' => $summary['paid_booking_value'],
                    'recorded_earnings' => $summary['recorded_earnings'],
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function commissionRows(Request $request, array $range, int $limit): array
    {
        return Booking::query()
            ->whereNotNull('vendor_profile_id')
            ->where('payment_status', 'paid')
            ->whereNotIn('booking_status', ['cancelled'])
            ->when($range['from'], fn ($q) => $q->where('created_at', '>=', $range['from']))
            ->when($range['to'], fn ($q) => $q->where('created_at', '<=', $range['to']))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->latest()
            ->limit($limit)
            ->get(['booking_reference_id', 'travel_date', 'gross_amount', 'total_amount', 'platform_commission_amount', 'vendor_earning_amount'])
            ->map(fn (Booking $b): array => [
                'booking' => $b->booking_reference_id,
                'travel_date' => $b->travel_date?->toDateString(),
                'gross_value' => (string) ($b->gross_amount ?? $b->total_amount),
                'platform_commission' => (string) $b->platform_commission_amount,
                'vendor_earning' => (string) $b->vendor_earning_amount,
            ])->all();
    }

    /**
     * Outstanding customer balance: total − paid + processed refunds
     * over non-cancelled bookings. Derived, never stored.
     */
    public static function outstandingBalance(): string
    {
        $total = (float) Booking::whereNotIn('booking_status', ['cancelled'])->sum('total_amount');
        $paid = (float) BookingPayment::whereHas('booking', fn ($q) => $q->whereNotIn('booking_status', ['cancelled']))->sum('amount');
        $refunded = (float) BookingRefund::where('status', 'processed')->sum('amount');

        return number_format($total - $paid + $refunded, 2, '.', '');
    }

    public static function paymentsCollected(?string $from = null, ?string $to = null): string
    {
        $query = BookingPayment::query();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return number_format((float) $query->sum('amount'), 2, '.', '');
    }
}
