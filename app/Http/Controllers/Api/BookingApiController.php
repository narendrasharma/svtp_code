<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

class BookingApiController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    public function index(\Illuminate\Http\Request $request)
    {
        return BookingResource::collection(
            $request->user()->bookings()->with('package')->latest()->paginate(10)
        );
    }

    public function store(StoreBookingRequest $request)
    {
        $package = TourPackage::findOrFail($request->package_id);
        $totalAmount = $package->effective_price
            * ($request->total_adults + ($request->total_children * 0.5));

        $booking = DB::transaction(fn () => Booking::create([
            'user_id' => $request->user()->id,
            'package_id' => $package->id,
            'travel_date' => $request->travel_date,
            'total_adults' => $request->total_adults,
            'total_children' => $request->total_children ?? 0,
            'total_amount' => $totalAmount,
        ]));

        $order = $this->payments->createRazorpayOrder($booking);

        return response()->json([
            'booking' => new BookingResource($booking),
            'razorpay_order' => $order,
        ]);
    }
}
