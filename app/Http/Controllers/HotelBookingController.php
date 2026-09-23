<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\HotelBookingService;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelBookingController extends Controller
{
    public function __construct(protected HotelBookingService $bookings) {}

    public function create(Request $request, string $slug): Response
    {
        if (! HotelSettings::enabled('hotel.booking.enabled')) {
            abort(404);
        }

        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'min:1'],
            'rate_plan_id' => ['required', 'integer', 'min:1'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ]);

        $property = Property::published()
            ->with(['propertyType:id,name', 'city:id,name', 'destination:id,name', 'images'])
            ->where('slug', $slug)
            ->firstOrFail();
        $room = $property->roomTypes()->active()->whereKey($data['room_type_id'])->firstOrFail();
        $plan = $room->ratePlans()->active()->whereKey($data['rate_plan_id'])->firstOrFail();
        $primary = $property->images->firstWhere('is_primary', true) ?? $property->images->first();

        return Inertia::render('Hotels/Booking', [
            'property' => [
                'name' => $property->name,
                'slug' => $property->slug,
                'type' => $property->propertyType?->name,
                'city' => $property->city?->name,
                'destination' => $property->destination?->name,
                'image' => $primary?->url(),
            ],
            'selection' => [
                'room_type_id' => $room->id,
                'rate_plan_id' => $plan->id,
                'room_name' => $room->name,
                'rate_name' => $plan->name,
                'meal_plan' => $plan->meal_plan,
                'cancellation_mode' => $plan->cancellation_mode,
                'cancellation_note' => $plan->cancellation_note,
            ],
            'stay' => [
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'rooms' => (int) $data['rooms'],
                'adults' => (int) $data['adults'],
                'children' => (int) ($data['children'] ?? 0),
            ],
            'customer' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone' => $request->user()->phone,
            ],
            'requiresTerms' => HotelSettings::enabled('hotel.booking.require_terms_acceptance'),
            'seo' => [
                'title' => __('common.complete_booking').' · '.$property->name,
                'description' => __('common.complete_booking_description'),
            ],
        ]);
    }

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
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_email' => ['required', 'email', 'max:150'],
            'guest_phone' => ['required', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'quote_fingerprint' => ['nullable', 'string', 'size:64'],
            'terms_accepted' => ['nullable', 'boolean'],
        ]);

        $property = Property::published()->where('slug', $slug)->firstOrFail();
        $room = $property->roomTypes()->active()->whereKey($data['room_type_id'])->firstOrFail();
        $plan = $room->ratePlans()->active()->whereKey($data['rate_plan_id'])->firstOrFail();

        if ((int) $plan->property_id !== $property->id) {
            abort(404);
        }

        $booking = $this->bookings->create($data, $request->user(), $request->user());

        return redirect()->route('hotel-booking.confirmation', $booking);
    }
}
