<?php

namespace App\Http\Controllers;

use App\Models\HotelRatePlan;
use App\Models\Property;
use App\Services\HotelBookingService;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HotelBookingController extends Controller
{
    public function __construct(protected HotelBookingService $bookings) {}

    public function store(Request $request, string $slug): RedirectResponse
    {
        if (! HotelSettings::enabled('hotel.booking.enabled')) {
            abort(404);
        }

        if (! $request->user()) {
            return redirect()->route('login')->with('status', 'Please sign in to reserve a hotel room.');
        }

        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'min:1'],
            'rate_plan_id' => ['required', 'integer', 'min:1'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d'],
            'rooms' => ['required', 'integer', 'min:1'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['sometimes', 'integer', 'min:0'],
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_email' => ['required', 'email', 'max:150'],
            'guest_phone' => ['required', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'quote_fingerprint' => ['nullable', 'string', 'size:64'],
            'terms_accepted' => ['nullable', 'boolean'],
        ]);

        $property = Property::published()->where('slug', $slug)->firstOrFail();
        $planProperty = (int) optional(HotelRatePlan::find($data['rate_plan_id']))->property_id;
        if ($planProperty !== $property->id) {
            abort(404);
        }

        $booking = $this->bookings->create($data, $request->user(), $request->user());

        return redirect()->route('hotel-booking.confirmation', $booking);
    }
}
