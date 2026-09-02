<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveManualBookingRequest;
use App\Models\Booking;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
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

    public function create()
    {
        return Inertia::render('Admin/Bookings/Form', [
            'packages' => TourPackage::active()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(SaveManualBookingRequest $request): RedirectResponse
    {
        Booking::create($request->validated() + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.bookings.index')->with('flash', 'Manual booking created.');
    }

    public function edit(Booking $booking)
    {
        return Inertia::render('Admin/Bookings/Form', [
            'booking' => $booking,
            'packages' => TourPackage::active()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function update(SaveManualBookingRequest $request, Booking $booking): RedirectResponse
    {
        $booking->update($request->validated());

        return redirect()->route('admin.bookings.index')->with('flash', 'Booking updated.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $booking->delete();

        return back()->with('flash', 'Booking deleted.');
    }
}
