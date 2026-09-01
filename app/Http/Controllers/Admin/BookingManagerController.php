<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingManagerController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::with('package', 'user')
            ->when($request->status, fn ($q) => $q->where('booking_status', $request->status))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Bookings', compact('bookings'));
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate(['booking_status' => ['required', 'in:confirmed,completed,cancelled']]);
        $booking->update(['booking_status' => $request->booking_status]);

        return back()->with('flash', 'Booking status updated.');
    }
}
