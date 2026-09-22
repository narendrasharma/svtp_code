<?php

namespace App\Http\Controllers\Vendor\Hotel;

use App\Enums\HotelBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\Property;
use App\Services\HotelBookingService;
use App\Services\HotelOperationsService;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function __construct(protected HotelOperationsService $operations) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        $properties = Property::where('vendor_profile_id', $profile->id)->orderBy('name')->get(['id', 'name', 'timezone']);
        $selected = $request->integer('property_id');
        $selectedProperties = $selected ? $properties->where('id', $selected) : $properties;
        abort_if($selected && $selectedProperties->isEmpty(), 404);
        $date = $request->date('date')?->toDateString() ?? ($selectedProperties->count() === 1 ? $this->operations->propertyToday($selectedProperties->first()) : now(HotelSettings::defaultTimezone())->toDateString());

        return Inertia::render('Vendor/Hotel/Operations', ['properties' => $properties, 'propertyId' => $selected, 'dashboard' => $this->operations->dashboard($selectedProperties, $date)]);
    }

    public function status(Request $request, HotelBooking $booking, HotelBookingService $bookings): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && (int) $booking->vendor_profile_id === (int) $profile->id, 404);
        $data = $request->validate(['status' => ['required', 'in:checked_in,checked_out,completed,no_show']]);
        $bookings->changeStatus($booking, HotelBookingStatus::from($data['status']), $request->user());

        return back()->with('flash', 'Hotel booking status updated.');
    }
}
