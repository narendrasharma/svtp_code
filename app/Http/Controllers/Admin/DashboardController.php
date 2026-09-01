<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourPackage;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_bookings' => Booking::count(),
                'revenue' => Booking::where('payment_status', 'paid')->sum('total_amount'),
                'active_tours' => TourPackage::active()->count(),
                'pending_reviews' => \App\Models\Review::where('is_approved', false)->count(),
            ],
            'recentBookings' => Booking::with('package', 'user')->latest()->take(8)->get(),
        ]);
    }
}
