<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\CouponRedemption;
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

        return [
            'customers' => $this->rangeQuery(User::where('role', 'customer'), 'created_at', $from, $to)->count(),
            'vendors_total' => $this->rangeQuery(VendorProfile::query(), 'created_at', $from, $to)->count(),
            'vendors_approved' => $this->rangeQuery(VendorProfile::where('is_active', true)->whereNotNull('approved_at'), 'created_at', $from, $to)->count(),
            'pending_applications' => VendorApplication::where('status', 'pending')->count(),
            'active_tours' => TourPackage::where('is_active', true)->where('moderation_status', 'approved')->count(),
            'bookings_total' => (clone $allBookings)->count(),
            'bookings_paid' => (clone $bookings)->count(),
            'gross_booking_value' => $this->decimal((clone $bookings)->sum(DB::raw('COALESCE(gross_amount, total_amount)'))),
            'platform_commission' => $this->decimal((clone $bookings)->sum('platform_commission_amount')),
            'vendor_earnings' => $this->decimal((clone $bookings)->sum('vendor_earning_amount')),
            'refunded_amount' => $this->decimal((clone $refunds)->sum('amount')),
            'pending_withdrawals' => VendorWithdrawalRequest::where('status', 'pending')->count(),
            'pending_withdrawal_amount' => $this->decimal(VendorWithdrawalRequest::where('status', 'pending')->sum('amount')),
            'top_tours' => $this->topTours($from, $to, null),
            'top_vendors' => $this->topVendors($from, $to),
            'coupons' => $this->couponMetrics($from, $to, null),
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
