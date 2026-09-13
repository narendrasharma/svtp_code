<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Existing statistics
        $stats = [
            'total_bookings'   => Booking::count(),
            'revenue'          => Booking::where('payment_status', 'paid')->sum('total_amount'),
            'active_tours'     => TourPackage::active()->count(),
            'pending_reviews'  => \App\Models\Review::where('is_approved', false)->count(),

            // New statistics
            'total_tour_packages' => TourPackage::count(),
            'total_categories'    => TourCategory::count(),
            'total_tags'          => Tag::count(),
            'total_destinations'  => Destination::count(),
            'total_places'        => Place::count(),
            'total_users'         => User::count(),
        ];

        // -----------------------------------------------------------------
        // 1. Recent Tour Packages (latest 5)
        // -----------------------------------------------------------------
        $recentTourPackages = TourPackage::orderByDesc('created_at')
            ->take(5)
            ->get(['id', 'title', 'price', 'duration', 'is_active', 'created_at']);

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
            'active'   => TourPackage::where('is_active', true)->count(),
            'inactive' => TourPackage::where('is_active', false)->count(),
        ];

        // -----------------------------------------------------------------
        // 4. Chart data – packages created per month for the last 6 months
        // -----------------------------------------------------------------
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();

        $packageCreationRaw = TourPackage::selectRaw('YEAR(created_at) as yr, MONTH(created_at) as mo, COUNT(*) as cnt')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('yr', 'mo')
            ->orderBy('yr')
            ->orderBy('mo')
            ->get();

        // Transform to a format suitable for the chart component
        $packageCreationChart = collect();
        for ($i = 0; $i < 6; $i++) {
            $date = Carbon::now()->subMonths(5 - $i)->startOfMonth(); // oldest first
            $label = $date->format('M Y');
            $match = $packageCreationRaw->firstWhere('yr', $date->year)->firstWhere('mo', $date->month);
            $value = $match ? $match->cnt : 0;
            $packageCreationChart->push(['label' => $label, 'value' => $value]);
        }

        return Inertia::render('Admin/Dashboard', [
            'stats'                 => $stats,
            'recentBookings'        => Booking::with('package', 'user')
                ->latest()
                ->take(8)
                ->get(),
            // New payload sections
            'recentTourPackages'    => $recentTourPackages,
            'topDestinations'       => $topDestinations,
            'packageStats'          => $packageStats,
            'packageCreationChart'  => $packageCreationChart,
        ]);
    }
}
