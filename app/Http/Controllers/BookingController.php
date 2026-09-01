<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    public function checkout(TourPackage $package)
    {
        return Inertia::render('Booking/Checkout', compact('package'));
    }

    public function store(StoreBookingRequest $request)
    {
        $package = TourPackage::findOrFail($request->package_id);

        $totalAmount = $package->effective_price
            * ($request->total_adults + ($request->total_children * 0.5));

        $booking = DB::transaction(function () use ($request, $package, $totalAmount) {
            return Booking::create([
                'user_id' => $request->user()->id,
                'package_id' => $package->id,
                'travel_date' => $request->travel_date,
                'total_adults' => $request->total_adults,
                'total_children' => $request->total_children ?? 0,
                'total_amount' => $totalAmount,
                'payment_status' => 'pending',
                'booking_status' => 'confirmed',
            ]);
        });

        $order = $request->gateway === 'paytm'
            ? $this->payments->createPaytmOrder($booking)
            : $this->payments->createRazorpayOrder($booking);

        return Inertia::render('Booking/Payment', compact('booking', 'order'));
    }

    public function confirmPayment(Booking $booking)
    {
        // NOTE: verify signature/checksum server-side before marking paid.
        // Wired here as the integration point — see PaymentService.
        $booking->update(['payment_status' => 'paid']);

        return redirect()->route('booking.invoice', $booking)->with('flash', 'Payment confirmed!');
    }

    public function history()
    {
        $bookings = auth()->user()->bookings()->with('package')->latest()->paginate(10);

        return Inertia::render('Booking/History', compact('bookings'));
    }
}
