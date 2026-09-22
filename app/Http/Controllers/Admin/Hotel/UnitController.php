<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRoomUnit;
use App\Models\Property;
use App\Services\HotelRoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin physical room unit management, nested under a property (12B.2).
 * Units are operational/internal; no public UI in this phase.
 */
class UnitController extends Controller
{
    public function __construct(protected HotelRoomService $rooms) {}

    public function index(Property $property): Response
    {
        $units = $property->roomUnits()->with('roomType:id,name')->orderBy('unit_name')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Hotel/RoomUnits/Index', [
            'property' => $property->only(['id', 'name', 'slug', 'status']),
            'units' => $units,
            'roomTypes' => $property->roomTypes()->orderBy('sort_order')->get(['id', 'name']),
            'statuses' => HotelRoomUnit::STATUSES,
        ]);
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate(HotelRoomService::roomUnitRules());
        $this->rooms->createUnit($property, $data);

        return back()->with('flash', 'Room unit added.');
    }

    public function update(Request $request, HotelRoomUnit $unit): RedirectResponse
    {
        $data = $request->validate(HotelRoomService::roomUnitRules());
        $property = $unit->property;
        abort_unless($property !== null, 404);
        $this->rooms->updateUnit($property, $unit, $data);

        return back()->with('flash', 'Room unit updated.');
    }

    public function destroy(HotelRoomUnit $unit): RedirectResponse
    {
        $property = $unit->property;
        abort_unless($property !== null, 404);
        $this->rooms->deleteUnit($property, $unit);

        return back()->with('flash', 'Room unit archived.');
    }
}
