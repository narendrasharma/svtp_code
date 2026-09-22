<?php

namespace App\Http\Controllers\Vendor\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\VendorProfile;
use App\Services\HotelAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor inventory calendar (12B.3). Own properties only — every lookup
 * is ownership-scoped (404 otherwise) and the room's property is
 * re-verified server-side, never trusted from the browser.
 */
class InventoryController extends Controller
{
    public function __construct(protected HotelAvailabilityService $availability) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        return $profile;
    }

    protected function scopedRoom(Request $request, int $roomTypeId): HotelRoomType
    {
        $profile = $this->profile($request);
        $roomType = HotelRoomType::findOrFail($roomTypeId);
        abort_unless(
            $roomType->property !== null && (int) $roomType->property->vendor_profile_id === (int) $profile->id,
            404
        );

        return $roomType;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);
        $properties = Property::where('vendor_profile_id', $profile->id)->orderBy('name')->get(['id', 'name', 'status']);

        $propertyId = $request->integer('property_id') ?: null;
        $property = $propertyId
            ? Property::where('vendor_profile_id', $profile->id)->find($propertyId)
            : $properties->first();

        $roomTypes = $property
            ? HotelRoomType::where('property_id', $property->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'status', 'inventory_mode', 'total_units'])
            : collect();

        $roomType = null;
        $requestedRoom = $request->integer('room_type_id') ?: null;

        if ($requestedRoom) {
            $candidate = HotelRoomType::find($requestedRoom);

            if ($candidate && $property && (int) $candidate->property_id === (int) $property->id) {
                $roomType = $candidate;
            }
        } else {
            $roomType = $roomTypes->first();
        }

        $start = $request->input('start');
        $end = $request->input('end');

        if (! is_string($start) || ! is_string($end)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $start = now()->format('Y-m-01');
            $end = now()->endOfMonth()->toDateString();
        }

        $rows = [];
        $structural = null;

        if ($roomType) {
            $rows = $this->availability->calendar($roomType, $start, $end);
            $structural = $this->availability->structuralCapacity($roomType);
        }

        return Inertia::render('Vendor/Hotel/Inventory/Index', [
            'properties' => $properties,
            'roomTypes' => $roomTypes,
            'propertyId' => $property?->id,
            'roomTypeId' => $roomType?->id,
            'start' => $start,
            'end' => $end,
            'structuralCapacity' => $structural,
            'inventoryMode' => $roomType?->inventory_mode,
            'rows' => $rows,
            'maxBulkDays' => $this->availability->maxBulkDays(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'capacity_override' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'blocked_units' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'stop_sell' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $roomType = $this->scopedRoom($request, (int) $data['room_type_id']);
        $this->availability->setDay($roomType, $data['date'], $this->ops($data), $request->user());

        return back()->with('flash', 'Inventory updated.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'capacity_override' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'blocked_units' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'stop_sell' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'clear' => ['sometimes', 'boolean'],
        ]);

        $roomType = $this->scopedRoom($request, (int) $data['room_type_id']);

        if ($request->boolean('clear')) {
            $deleted = $this->availability->clearRange($roomType, $data['start_date'], $data['end_date'], $request->user());

            return back()->with('flash', "Overrides cleared ({$deleted} rows removed).");
        }

        $summary = $this->availability->applyRange($roomType, $data['start_date'], $data['end_date'], $this->ops($data), $request->user());

        return back()->with('flash', "Inventory updated for {$summary['dates_affected']} dates.");
    }

    public function clear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $roomType = $this->scopedRoom($request, (int) $data['room_type_id']);
        $this->availability->clearDay($roomType, $data['date'], $request->user());

        return back()->with('flash', 'Override cleared; structural default restored.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{capacity_override: ?int, blocked_units: int, stop_sell: bool, note: ?string}
     */
    protected function ops(array $data): array
    {
        return [
            'capacity_override' => $data['capacity_override'] ?? null,
            'blocked_units' => $data['blocked_units'] ?? 0,
            'stop_sell' => (bool) ($data['stop_sell'] ?? false),
            'note' => $data['note'] ?? null,
        ];
    }
}
