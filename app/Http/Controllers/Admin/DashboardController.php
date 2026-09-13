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
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                // Existing statistics
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
                // If a Vendor model exists in the future, you can uncomment the line below
                // 'total_vendors'       => Vendor::count(),
            ],
            'recentBookings' => Booking::with('package', 'user')
                ->latest()
                ->take(8)
                ->get(),
        ]);
    }
}
