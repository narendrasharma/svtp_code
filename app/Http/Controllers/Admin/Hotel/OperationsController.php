<?php

namespace App\Http\Controllers\Admin\Hotel;

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
        $properties = Property::query()->with('vendorProfile:id,business_name')->orderBy('name')->get(['id', 'name', 'timezone', 'vendor_profile_id']);
        $selected = $request->integer('property_id');
        $selectedProperties = $selected ? $properties->where('id', $selected) : $properties;
        abort_if($selected && $selectedProperties->isEmpty(), 404);
        $date = $request->date('date')?->toDateString() ?? ($selectedProperties->count() === 1 ? $this->operations->propertyToday($selectedProperties->first()) : now(HotelSettings::defaultTimezone())->toDateString());

        return Inertia::render('Admin/Hotel/Operations', ['properties' => $properties, 'propertyId' => $selected, 'dashboard' => $this->operations->dashboard($selectedProperties, $date)]);
    }

    public function status(Request $request, HotelBooking $booking, HotelBookingService $bookings): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:checked_in,checked_out,completed,no_show']]);
        $bookings->changeStatus($booking, HotelBookingStatus::from($data['status']), $request->user());

        return back()->with('flash', 'Hotel booking status updated.');
    }
}
