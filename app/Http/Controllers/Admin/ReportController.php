<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\Lead;
use App\Models\VendorProfile;
use App\Services\MarketplaceAnalyticsService;
use Illuminate\Http\Request;
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
        $tab = in_array($request->string('tab')->toString(), ['bookings', 'payments', 'leads', 'vendors', 'commission'], true)
            ? $request->string('tab')->toString()
            : 'bookings';

        return Inertia::render('Admin/Reports/Index', [
            'tab' => $tab,
            'range' => $range,
            'filters' => $request->only(['tab', 'preset', 'from', 'to', 'status', 'source', 'vendor_id']),
            'summary' => $analytics->adminSummary($range['from'], $range['to']),
            'rows' => $this->rows($tab, $request, $range),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function export(Request $request, MarketplaceAnalyticsService $analytics): StreamedResponse
    {
        $range = $analytics->resolveRange($request->only(['preset', 'from', 'to']));
        $tab = in_array($request->string('tab')->toString(), ['bookings', 'payments', 'leads', 'vendors', 'commission'], true)
            ? $request->string('tab')->toString()
            : 'bookings';

        $rows = $this->rows($tab, $request, $range, true);

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
