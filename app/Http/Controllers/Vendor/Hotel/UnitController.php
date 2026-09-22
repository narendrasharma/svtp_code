<?php

namespace App\Http\Controllers\Vendor\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRoomUnit;
use App\Models\Property;
use App\Services\HotelRoomService;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor physical room unit management for OWN properties (12B.2).
 * Units are optional and operational/internal.
 */
class UnitController extends Controller
{
    public function __construct(protected HotelRoomService $rooms) {}

    protected function property(Request $request, int $propertyId): Property
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);
        abort_unless(HotelSettings::enabled('hotel.rooms.units_enabled'), 403, 'Room units are disabled.');

        return Property::whereKey($propertyId)->where('vendor_profile_id', $profile->id)->firstOrFail();
    }

    protected function scoped(Request $request, HotelRoomUnit $unit): HotelRoomUnit
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);
        abort_unless(HotelSettings::enabled('hotel.rooms.units_enabled'), 403, 'Room units are disabled.');
        abort_unless($unit->property !== null && (int) $unit->property->vendor_profile_id === (int) $profile->id, 404);

        return $unit;
    }

    public function index(Request $request, Property $property): Response
    {
        $property = $this->property($request, $property->id);
        $units = $property->roomUnits()->with('roomType:id,name')->orderBy('unit_name')->paginate(20)->withQueryString();

        return Inertia::render('Vendor/Hotel/RoomUnits/Index', [
            'property' => $property->only(['id', 'name', 'slug', 'status']),
            'units' => $units,
            'roomTypes' => $property->roomTypes()->orderBy('sort_order')->get(['id', 'name']),
            'statuses' => HotelRoomUnit::STATUSES,
        ]);
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        $property = $this->property($request, $property->id);
        $data = $request->validate(HotelRoomService::roomUnitRules());
        $this->rooms->createUnit($property, $data);

        return back()->with('flash', 'Room unit added.');
    }

    public function update(Request $request, HotelRoomUnit $unit): RedirectResponse
    {
        $unit = $this->scoped($request, $unit);
        $data = $request->validate(HotelRoomService::roomUnitRules());
        $this->rooms->updateUnit($unit->property, $unit, $data);

        return back()->with('flash', 'Room unit updated.');
    }

    public function destroy(Request $request, HotelRoomUnit $unit): RedirectResponse
    {
        $unit = $this->scoped($request, $unit);
        $this->rooms->deleteUnit($unit->property, $unit);

        return back()->with('flash', 'Room unit archived.');
    }
}
