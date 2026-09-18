<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\Destination;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Place;
use App\Models\Quotation;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\Tag;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorDocument;
use App\Models\VendorWithdrawalRequest;
use App\Services\MarketplaceAnalyticsService;
use App\Services\SystemHealthService;
use App\Support\ModuleManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Existing statistics
        $stats = [
            'total_bookings' => Booking::count(),
            'revenue' => Booking::where('payment_status', 'paid')->sum('total_amount'),
            'active_tours' => TourPackage::active()->count(),
            'pending_reviews' => Review::where('is_approved', false)->count(),

            // New statistics
            'total_tour_packages' => TourPackage::count(),
            'total_categories' => TourCategory::count(),
            'total_tags' => Tag::count(),
            'total_destinations' => Destination::count(),
            'total_places' => Place::count(),
            'total_users' => User::count(),
        ];

        // Phase 11 marketplace analytics (server-side aggregates, date
        // presets + custom range). Existing stats above are kept for BC.
        $analyticsService = app(MarketplaceAnalyticsService::class);
        $range = $analyticsService->resolveRange(request()->only(['preset', 'from', 'to']));
        $analytics = $analyticsService->adminSummary($range['from'], $range['to']);
        $timeseries = $analyticsService->timeseries($range['from'], $range['to']);

        // -----------------------------------------------------------------
        // 1. Recent Tour Packages (latest 5)
        // -----------------------------------------------------------------
        // The table does NOT have a single "duration" column.
        // Use the real columns: duration_days and duration_nights.
        $recentTourPackages = TourPackage::orderByDesc('created_at')
            ->take(5)
            ->get([
                'id',
                'title',
                'price',
                'duration_days',
                'duration_nights',
                'is_active',
                'created_at',
            ]);

        // -----------------------------------------------------------------
        // 2. Top Destinations (by number of related tour packages)
        // -----------------------------------------------------------------
        $topDestinations = Destination::withCount('tourPackages')
            ->orderByDesc('tour_packages_count')
            ->take(5)
            ->get(['id', 'name', 'slug', 'tour_packages_count']);

        // -----------------------------------------------------------------
        // 3. Package Statistics (active / inactive breakdown)
        // -----------------------------------------------------------------
        $packageStats = [
            'active' => TourPackage::where('is_active', true)->count(),
            'inactive' => TourPackage::where('is_active', false)->count(),
        ];

        // -----------------------------------------------------------------
        // 4. Chart data – packages created per month for the last 6 months
        // -----------------------------------------------------------------
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();

        // Use the query builder (DB) to avoid returning Eloquent models,
        // which would expose query methods like where() on the result items.
        // Cross-driver date grouping (MySQL YEAR()/MONTH() vs SQLite
        // strftime) so the dashboard also renders in the isolated test DB.
        $packageCreationRaw = DB::table('tour_packages')
            ->when(DB::getDriverName() === 'sqlite', function ($query): void {
                $query->selectRaw("CAST(strftime('%Y', created_at) AS INTEGER) as yr, CAST(strftime('%m', created_at) AS INTEGER) as mo, COUNT(*) as cnt");
            }, function ($query): void {
                $query->selectRaw('YEAR(created_at) as yr, MONTH(created_at) as mo, COUNT(*) as cnt');
            })
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('yr', 'mo')
            ->orderBy('yr')
            ->orderBy('mo')
            ->get();

        // Transform to a format suitable for the chart component.
        // We need to match each of the last six months to the aggregated data.
        $packageCreationChart = collect();
        for ($i = 0; $i < 6; $i++) {
            // Oldest month first.
            $date = Carbon::now()->subMonths(5 - $i)->startOfMonth();
            $label = $date->format('M Y');

            // Find the aggregated row for this year/month.
            $match = $packageCreationRaw->firstWhere(function ($item) use ($date) {
                return $item->yr == $date->year && $item->mo == $date->month;
            });

            $value = $match ? $match->cnt : 0;
            $packageCreationChart->push(['label' => $label, 'value' => $value]);
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentBookings' => Booking::with('package', 'user')
                ->latest()
                ->take(8)
                ->get(),
            // New payload sections
            'recentTourPackages' => $recentTourPackages,
            'topDestinations' => $topDestinations,
            'packageStats' => $packageStats,
            'packageCreationChart' => $packageCreationChart,
            // Phase 11 marketplace analytics.
            'analytics' => $analytics,
            'analyticsRange' => $range,
            'timeseries' => $timeseries,
            // Phase 11.5D operations command center.
            'ops' => $this->operations($analytics, $range),
        ]);
    }

    /**
     * Operational command-center payload (11.5D).
     *
     * Financial semantics: Gross Booking Value (customer-paid totals) is
     * reported separately from platform commission, vendor earnings,
     * refunds, collected payments and outstanding balance. GBV is never
     * labelled revenue.
     *
     * @param  array<string, mixed>  $analytics
     * @param  array{preset: string, from: ?string, to: ?string}  $range
     * @return array<string, mixed>
     */
    protected function operations(array $analytics, array $range): array
    {
        $user = auth()->user();
        $toursEnabled = app(ModuleManager::class)->isEnabled(ModuleManager::TOURS);
        $canFinance = $user && $user->can('reports.view');

        $leadsTotal = Lead::count();
        $leadsWon = Lead::where('status', 'won')->count();

        $needsAttention = [
            [
                'key' => 'overdue_followups',
                'label' => 'Overdue follow-ups',
                'count' => LeadFollowUp::overdue()->count(),
                'url' => '/admin/follow-ups?filter=overdue',
                'permission' => 'leads.view',
            ],
            [
                'key' => 'quotations_expiring',
                'label' => 'Quotations expiring soon',
                'count' => $this->expiringQuotationsCount(),
                'url' => '/admin/quotations?filter=expiring',
                'permission' => 'quotations.view',
            ],
            [
                'key' => 'vendor_applications',
                'label' => 'Pending vendor applications',
                'count' => VendorApplication::where('status', 'pending')->count(),
                'url' => '/admin/vendor-applications',
                'permission' => 'vendors.view',
            ],
            [
                'key' => 'kyc_reviews',
                'label' => 'KYC documents awaiting review',
                'count' => VendorDocument::where('status', 'pending')->count(),
                'url' => '/admin/vendor-applications',
                'permission' => 'vendors.kyc_review',
            ],
            [
                'key' => 'tours_awaiting_approval',
                'label' => 'Tours awaiting approval',
                'count' => $toursEnabled ? TourPackage::where('moderation_status', 'pending_review')->count() : 0,
                'url' => '/admin/packages',
                'permission' => 'tours.approve',
                'module' => ModuleManager::TOURS,
            ],
            [
                'key' => 'cancellation_requests',
                'label' => 'Pending cancellation requests',
                'count' => BookingCancellationRequest::where('status', 'pending')->count(),
                'url' => '/admin/bookings',
                'permission' => 'bookings.cancel',
            ],
            [
                'key' => 'pending_withdrawals',
                'label' => 'Pending withdrawals',
                'count' => VendorWithdrawalRequest::where('status', 'pending')->count(),
                'url' => '/admin/withdrawals',
                'permission' => 'finance.view',
            ],
            [
                'key' => 'open_tickets',
                'label' => 'Open support tickets',
                'count' => SupportTicket::whereIn('status', ['open', 'pending_staff'])->count(),
                'url' => '/admin/support',
                'permission' => 'support.view',
            ],
            [
                'key' => 'high_priority_tickets',
                'label' => 'High-priority support tickets',
                'count' => SupportTicket::whereIn('status', ['open', 'pending_staff'])->whereIn('priority', ['high', 'urgent'])->count(),
                'url' => '/admin/support?priority=high',
                'permission' => 'support.view',
            ],
            [
                'key' => 'failed_jobs',
                'label' => 'Failed queue jobs',
                'count' => app(SystemHealthService::class)->failedJobs(),
                'url' => '/admin/system/failed-jobs',
                'permission' => 'system.jobs.manage',
            ],
        ];

        $modules = app(ModuleManager::class);

        // Module-aware for everyone: disabled modules never surface widgets.
        $needsAttention = array_values(array_filter(
            $needsAttention,
            fn (array $item): bool => ! isset($item['module']) || $modules->isEnabled($item['module'])
        ));

        // Permission-aware: staff only see items they are allowed to act on.
        if ($user && ! $user->isSuperAdmin()) {
            $needsAttention = array_values(array_filter(
                $needsAttention,
                fn (array $item): bool => $user->can($item['permission'])
            ));
        }

        return [
            'kpis' => [
                'customers' => User::where('role', 'customer')->count(),
                'bookings_total' => Booking::count(),
                'gross_booking_value' => $canFinance ? $analytics['gross_booking_value'] ?? null : null,
                'platform_commission' => $canFinance ? $analytics['platform_commission'] ?? null : null,
                'vendor_earnings' => $canFinance ? $analytics['vendor_earnings'] ?? null : null,
                'refunded_amount' => $canFinance ? $analytics['refunded_amount'] ?? null : null,
                'outstanding_balance' => $canFinance ? ReportController::outstandingBalance() : null,
                'payments_collected' => $canFinance ? ReportController::paymentsCollected($range['from'], $range['to']) : null,
                'leads_total' => $leadsTotal,
                'leads_won' => $leadsWon,
                'lead_conversion_rate' => $leadsTotal > 0 ? round($leadsWon / $leadsTotal * 100, 1) : 0,
                'open_support_tickets' => SupportTicket::whereIn('status', ['open', 'pending_staff'])->count(),
                'pending_withdrawals' => VendorWithdrawalRequest::where('status', 'pending')->count(),
            ],
            'finance_visible' => (bool) $canFinance,
            'tours_enabled' => $toursEnabled,
            'needs_attention' => $needsAttention,
            'recent_activity' => ActivityLog::with('actor:id,name')
                ->latest()
                ->take(10)
                ->get(['id', 'actor_user_id', 'event', 'module', 'description', 'created_at']),
        ];
    }

    protected function expiringQuotationsCount(): int
    {
        return Quotation::whereIn('status', ['sent', 'viewed'])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', today())
            ->whereDate('valid_until', '<=', today()->addDays(7))
            ->count();
    }
}
