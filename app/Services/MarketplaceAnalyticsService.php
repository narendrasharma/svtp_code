<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\CouponRedemption;
use App\Models\HotelBooking;
use App\Models\TaxiBooking;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorProfile;
use App\Models\VendorWithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace analytics (Phase 11).
 *
 * Server-side aggregates only — controllers pass a date range, Vue never
 * loads raw bookings. Money scope is PAID + NOT CANCELLED throughout so
 * Gross Booking Value, commission and earnings stay comparable.
 *
 * Revenue semantics (never conflate):
 * - Gross Booking Value = customer-paid booking totals (snapshots).
 * - Platform Commission = platform cut of GBV (vendor tours only).
 * - Vendor Earnings = vendor cut of GBV.
 * - Refunded Amount = processed manual refunds (accounting only).
 */
class MarketplaceAnalyticsService
{
    /**
     * @return array{preset: string, from: ?string, to: ?string}
     */
    public function resolveRange(array $input): array
    {
        $hasCustom = isset($input['from']) && trim((string) $input['from']) !== ''
            || isset($input['to']) && trim((string) $input['to']) !== '';
        $preset = (string) ($input['preset'] ?? ($hasCustom ? 'custom' : 'last30'));
        $today = Carbon::today();

        $from = null;
        $to = null;

        if ($preset === 'today') {
            $from = $today->copy()->startOfDay();
            $to = $today->copy()->endOfDay();
        } elseif ($preset === 'last7') {
            $from = $today->copy()->subDays(6)->startOfDay();
            $to = $today->copy()->endOfDay();
        } elseif ($preset === 'last30') {
            $from = $today->copy()->subDays(29)->startOfDay();
            $to = $today->copy()->endOfDay();
        } elseif ($preset === 'month') {
            $from = $today->copy()->startOfMonth();
            $to = $today->copy()->endOfDay();
        } else {
            $from = $this->parseDate(isset($input['from']) ? (string) $input['from'] : null, true);
            $to = $this->parseDate(isset($input['to']) ? (string) $input['to'] : null, false);
        }

        if ($preset !== 'today' && $preset !== 'last7' && $preset !== 'last30' && $preset !== 'month') {
            $preset = ($from !== null || $to !== null) ? 'custom' : 'all';
        }

        return [
            'preset' => $preset,
            'from' => $from?->toDateTimeString(),
            'to' => $to?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSummary(?string $from, ?string $to): array
    {
        $bookings = $this->moneyBookingsQuery(null, $from, $to);
        $allBookings = $this->rangeQuery(Booking::query(), 'created_at', $from, $to);

        $refunds = $this->rangeQuery(BookingRefund::where('status', 'processed'), 'processed_at', $from, $to);
        $tourMoneyByCurrency = $this->currencyTotals(clone $bookings, 'COALESCE(gross_amount, total_amount)');
        $singleTourCurrency = count($tourMoneyByCurrency) === 1;

        return [
            'customers' => $this->rangeQuery(User::where('role', 'customer'), 'created_at', $from, $to)->count(),
            'vendors_total' => $this->rangeQuery(VendorProfile::query(), 'created_at', $from, $to)->count(),
            'vendors_approved' => $this->rangeQuery(VendorProfile::where('is_active', true)->whereNotNull('approved_at'), 'created_at', $from, $to)->count(),
            'pending_applications' => VendorApplication::where('status', 'pending')->count(),
            'active_tours' => TourPackage::where('is_active', true)->where('moderation_status', 'approved')->count(),
            'bookings_total' => (clone $allBookings)->count(),
            'bookings_paid' => (clone $bookings)->count(),
            'gross_booking_value' => $singleTourCurrency ? $this->decimal((clone $bookings)->sum(DB::raw('COALESCE(gross_amount, total_amount)'))) : null,
            'platform_commission' => $singleTourCurrency ? $this->decimal((clone $bookings)->sum('platform_commission_amount')) : null,
            'vendor_earnings' => $singleTourCurrency ? $this->decimal((clone $bookings)->sum('vendor_earning_amount')) : null,
            'refunded_amount' => $this->decimal((clone $refunds)->sum('amount')),
            'gross_booking_value_by_currency' => $tourMoneyByCurrency,
            'pending_withdrawals' => VendorWithdrawalRequest::where('status', 'pending')->count(),
            'pending_withdrawal_amount' => $this->decimal(VendorWithdrawalRequest::where('status', 'pending')->sum('amount')),
            'top_tours' => $this->topTours($from, $to, null),
            'top_vendors' => $this->topVendors($from, $to),
            'coupons' => $this->couponMetrics($from, $to, null),
            'system' => $this->systemSummary($from, $to),
        ];
    }

    /**
     * System-wide booking visibility. Money remains grouped by its stored
     * transaction currency; no visitor display conversion is used here.
     *
     * @return array<string, mixed>
     */
    public function systemSummary(?string $from, ?string $to): array
    {
        $tour = $this->rangeQuery(Booking::query(), 'created_at', $from, $to);
        $hotel = $this->rangeQuery(HotelBooking::query(), 'created_at', $from, $to);
        $taxi = $this->rangeQuery(TaxiBooking::query(), 'created_at', $from, $to);
        $tourValue = (clone $tour)->where('booking_status', '!=', BookingStatus::Cancelled->value);
        $hotelValue = (clone $hotel)->whereNotIn('status', ['cancelled', 'no_show']);
        $taxiValue = (clone $taxi)->whereNotIn('status', ['cancelled', 'no_show']);

        $modules = [
            'tours' => [
                'label' => 'Tours',
                'bookings' => (clone $tour)->count(),
                'value_by_currency' => $this->currencyTotals($tourValue, 'total_amount'),
            ],
            'hotels' => [
                'label' => 'Hotels',
                'bookings' => (clone $hotel)->count(),
                'value_by_currency' => $this->currencyTotals($hotelValue, 'total'),
            ],
            'taxi' => [
                'label' => 'Taxi',
                'bookings' => (clone $taxi)->count(),
                'value_by_currency' => $this->currencyTotals($taxiValue, 'total_amount'),
            ],
        ];

        return [
            'bookings' => array_sum(array_column($modules, 'bookings')),
            'new_customers' => $this->rangeQuery(User::where('role', 'customer'), 'created_at', $from, $to)->count(),
            'modules' => $modules,
            'status_mix' => $this->systemStatusMix($from, $to),
            'trend' => $this->systemTrend($from, $to),
            'recent_activity' => $this->recentSystemBookings($from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vendorSummary(VendorProfile $profile, ?string $from, ?string $to): array
    {
        $bookings = $this->moneyBookingsQuery($profile->id, $from, $to);
        $assigned = $this->rangeQuery(Booking::where('vendor_profile_id', $profile->id), 'created_at', $from, $to);

        $refunded = $this->decimal(
            BookingRefund::where('status', 'processed')
                ->whereIn('booking_id', (clone $assigned)->select('id'))
                ->when($from, fn ($q) => $q->where('processed_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('processed_at', '<=', $to))
                ->sum('amount')
        );

        $rating = DB::table('reviews')
            ->join('tour_packages', 'tour_packages.id', '=', 'reviews.package_id')
            ->where('tour_packages.vendor_profile_id', $profile->id)
            ->where('reviews.is_approved', true)
            ->selectRaw('AVG(reviews.rating) as avg_rating, COUNT(*) as reviews_count')
            ->first();

        return [
            'bookings_total' => (clone $assigned)->count(),
            'bookings_paid' => (clone $bookings)->count(),
            'paid_booking_value' => $this->decimal((clone $bookings)->sum(DB::raw('COALESCE(gross_amount, total_amount)'))),
            'recorded_earnings' => $this->decimal((clone $bookings)->sum('vendor_earning_amount')),
            'refunded_amount' => $refunded,
            'tours_count' => TourPackage::where('vendor_profile_id', $profile->id)->count(),
            'tours_active' => TourPackage::where('vendor_profile_id', $profile->id)->where('is_active', true)->where('moderation_status', 'approved')->count(),
            'average_rating' => $rating->avg_rating !== null ? round((float) $rating->avg_rating, 2) : null,
            'reviews_count' => (int) ($rating->reviews_count ?? 0),
            'coupons' => $this->couponMetrics($from, $to, $profile->id),
        ];
    }

    /**
     * Daily buckets for charts (bookings, GBV, commission). Capped at 90
     * points to keep payloads small.
     *
     * @return array<int, array{date: string, bookings: int, gross_booking_value: string, platform_commission: string}>
     */
    public function timeseries(?string $from, ?string $to, ?int $vendorProfileId = null): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::today()->subDays(29)->startOfDay();
        $end = $to ? Carbon::parse($to)->endOfDay() : Carbon::today()->endOfDay();

        if ($end->diffInDays($start) > 90) {
            $start = $end->copy()->subDays(89)->startOfDay();
        }

        $rows = $this->moneyBookingsQuery($vendorProfileId, $start->toDateTimeString(), $end->toDateTimeString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as bookings, SUM(COALESCE(gross_amount, total_amount)) as gbv, SUM(platform_commission_amount) as commission')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $points = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $points[] = [
                'date' => $key,
                'bookings' => (int) ($row->bookings ?? 0),
                'gross_booking_value' => $this->decimal($row->gbv ?? 0),
                'platform_commission' => $this->decimal($row->commission ?? 0),
            ];

            $cursor->addDay();
        }

        return $points;
    }

    /**
     * @return Builder<Booking>
     */
    protected function moneyBookingsQuery(?int $vendorProfileId, ?string $from, ?string $to)
    {
        $query = Booking::where('payment_status', PaymentStatus::Paid->value)
            ->where('booking_status', '!=', BookingStatus::Cancelled->value);

        if ($vendorProfileId !== null) {
            $query->where('vendor_profile_id', $vendorProfileId);
        }

        return $this->rangeQuery($query, 'created_at', $from, $to);
    }

    /**
     * @return Builder<TourPackage>
     */
    protected function topTours(?string $from, ?string $to, ?int $vendorProfileId): mixed
    {
        $query = Booking::where('payment_status', PaymentStatus::Paid->value)
            ->where('booking_status', '!=', BookingStatus::Cancelled->value)
            ->when($vendorProfileId !== null, fn ($q) => $q->where('vendor_profile_id', $vendorProfileId))
            ->when($from, fn ($q) => $q->where('bookings.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('bookings.created_at', '<=', $to))
            ->join('tour_packages', 'tour_packages.id', '=', 'bookings.package_id')
            ->selectRaw('bookings.package_id, tour_packages.title as title, COUNT(*) as paid_bookings, SUM(COALESCE(bookings.gross_amount, bookings.total_amount)) as paid_value')
            ->groupBy('bookings.package_id', 'tour_packages.title')
            ->orderByDesc('paid_bookings')
            ->limit(5);

        return $query->get()->map(fn ($row): array => [
            'package_id' => (int) $row->package_id,
            'title' => $row->title,
            'paid_bookings' => (int) $row->paid_bookings,
            'paid_value' => $this->decimal($row->paid_value),
        ])->all();
    }

    protected function topVendors(?string $from, ?string $to): array
    {
        return Booking::where('payment_status', PaymentStatus::Paid->value)
            ->where('booking_status', '!=', BookingStatus::Cancelled->value)
            ->whereNotNull('bookings.vendor_profile_id')
            ->when($from, fn ($q) => $q->where('bookings.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('bookings.created_at', '<=', $to))
            ->join('vendor_profiles', 'vendor_profiles.id', '=', 'bookings.vendor_profile_id')
            ->selectRaw('bookings.vendor_profile_id, vendor_profiles.business_name as business_name, COUNT(*) as paid_bookings, SUM(COALESCE(bookings.gross_amount, bookings.total_amount)) as paid_value')
            ->groupBy('bookings.vendor_profile_id', 'vendor_profiles.business_name')
            ->orderByDesc('paid_value')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'vendor_profile_id' => (int) $row->vendor_profile_id,
                'business_name' => $row->business_name,
                'paid_bookings' => (int) $row->paid_bookings,
                'paid_value' => $this->decimal($row->paid_value),
            ])->all();
    }

    /**
     * @return array{redemptions: int, discount_granted: string}
     */
    protected function couponMetrics(?string $from, ?string $to, ?int $vendorProfileId): array
    {
        $query = CouponRedemption::query()
            ->when($from, fn ($q) => $q->where('coupon_redemptions.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('coupon_redemptions.created_at', '<=', $to));

        if ($vendorProfileId !== null) {
            $query->whereIn('coupon_redemptions.booking_id', Booking::where('vendor_profile_id', $vendorProfileId)->select('id'));
        }

        return [
            'redemptions' => (clone $query)->count(),
            'discount_granted' => $this->decimal((clone $query)->sum('discount_amount')),
        ];
    }

    protected function rangeQuery(mixed $query, string $column, ?string $from, ?string $to): mixed
    {
        return $query
            ->when($from, fn ($q) => $q->where($column, '>=', $from))
            ->when($to, fn ($q) => $q->where($column, '<=', $to));
    }

    /** @return array<string, array{bookings:int, amount:string}> */
    protected function currencyTotals(mixed $query, string $amountExpression): array
    {
        return $query
            ->selectRaw("currency, COUNT(*) as bookings, SUM({$amountExpression}) as amount")
            ->whereNotNull('currency')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->mapWithKeys(fn ($row): array => [strtoupper((string) $row->currency) => [
                'bookings' => (int) $row->bookings,
                'amount' => $this->decimal($row->amount),
            ]])->all();
    }

    /** @return array<string, int> */
    protected function systemStatusMix(?string $from, ?string $to): array
    {
        $mix = ['pending' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0];
        $groups = [
            [Booking::query(), 'booking_status', ['pending' => 'pending', 'confirmed' => 'active', 'completed' => 'completed', 'cancelled' => 'cancelled']],
            [HotelBooking::query(), 'status', ['pending' => 'pending', 'confirmed' => 'active', 'checked_in' => 'active', 'checked_out' => 'active', 'completed' => 'completed', 'cancelled' => 'cancelled', 'no_show' => 'cancelled']],
            [TaxiBooking::query(), 'status', ['draft' => 'pending', 'quoted' => 'pending', 'confirmed' => 'active', 'driver_assigned' => 'active', 'en_route' => 'active', 'arrived' => 'active', 'passenger_on_board' => 'active', 'completed' => 'completed', 'cancelled' => 'cancelled', 'no_show' => 'cancelled']],
        ];

        foreach ($groups as [$query, $column, $mapping]) {
            $rows = $this->rangeQuery($query, 'created_at', $from, $to)
                ->select($column)
                ->selectRaw('COUNT(*) as count')
                ->groupBy($column)
                ->get();

            foreach ($rows as $row) {
                $status = method_exists($row, 'getRawOriginal')
                    ? $row->getRawOriginal($column)
                    : $row->{$column};
                $key = $mapping[(string) $status] ?? null;
                if ($key !== null) {
                    $mix[$key] += (int) $row->count;
                }
            }
        }

        return $mix;
    }

    /** @return array<int, array{date:string, bookings:int}> */
    protected function systemTrend(?string $from, ?string $to): array
    {
        $end = $to ? Carbon::parse($to)->endOfDay() : Carbon::today()->endOfDay();
        $start = $from ? Carbon::parse($from)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        if ($end->diffInDays($start) > 90) {
            $start = $end->copy()->subDays(89)->startOfDay();
        }

        $counts = collect();
        foreach ([Booking::query(), HotelBooking::query(), TaxiBooking::query()] as $query) {
            $rows = $this->rangeQuery($query, 'created_at', $start->toDateTimeString(), $end->toDateTimeString())
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->get();

            foreach ($rows as $row) {
                $counts[$row->day] = (int) ($counts[$row->day] ?? 0) + (int) $row->count;
            }
        }

        $trend = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $date = $cursor->toDateString();
            $trend[] = ['date' => $date, 'bookings' => $counts[$date] ?? 0];
        }

        return $trend;
    }

    /** @return array<int, array{module:string, reference:string, customer:?string, status:string, created_at:string}> */
    protected function recentSystemBookings(?string $from, ?string $to): array
    {
        $items = collect();
        $tour = $this->rangeQuery(Booking::query(), 'created_at', $from, $to)->latest()->limit(5)->get(['booking_reference_id', 'customer_name', 'booking_status', 'created_at']);
        $hotel = $this->rangeQuery(HotelBooking::query(), 'created_at', $from, $to)->latest()->limit(5)->get(['booking_number', 'guest_name', 'status', 'created_at']);
        $taxi = $this->rangeQuery(TaxiBooking::query(), 'created_at', $from, $to)->latest()->limit(5)->get(['reference', 'customer_name', 'status', 'created_at']);

        $tour->each(fn (Booking $booking) => $items->push(['module' => 'Tours', 'reference' => $booking->booking_reference_id, 'customer' => $booking->customer_name, 'status' => $booking->booking_status->value, 'created_at' => $booking->created_at?->toISOString()]));
        $hotel->each(fn (HotelBooking $booking) => $items->push(['module' => 'Hotels', 'reference' => $booking->booking_number, 'customer' => $booking->guest_name, 'status' => $booking->status->value, 'created_at' => $booking->created_at?->toISOString()]));
        $taxi->each(fn (TaxiBooking $booking) => $items->push(['module' => 'Taxi', 'reference' => $booking->reference, 'customer' => $booking->customer_name, 'status' => $booking->status, 'created_at' => $booking->created_at?->toISOString()]));

        return $items->sortByDesc('created_at')->take(10)->values()->all();
    }

    protected function parseDate(?string $value, bool $startOfDay): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);

            return $startOfDay ? $parsed->startOfDay() : $parsed->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
