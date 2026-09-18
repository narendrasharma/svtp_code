<?php

namespace App\Http\Controllers\Account;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $bookings = $request->user()->bookings();

        return Inertia::render('Account/Dashboard', [
            'stats' => [
                'total' => (clone $bookings)->count(),
                'upcoming' => (clone $bookings)
                    ->whereDate('travel_date', '>=', today())
                    ->where('booking_status', '!=', BookingStatus::Cancelled->value)
                    ->count(),
                'pending' => (clone $bookings)->where('booking_status', BookingStatus::Pending->value)->count(),
                'completed' => (clone $bookings)->where('booking_status', BookingStatus::Completed->value)->count(),
            ],
            'recentBookings' => (clone $bookings)
                ->with('package:id,title,slug')
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
