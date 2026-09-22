<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Enums\RoomTypeStatus;
use App\Http\Controllers\Controller;
use App\Models\HotelAmenity;
use App\Models\HotelBedType;
use App\Models\HotelRoomImage;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Services\HotelCustomFieldService;
use App\Services\HotelRoomService;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin room type management, nested under a property (12B.2).
 * Platform-wide access; staff authorization via the route map.
 */
class RoomController extends Controller
{
    public function __construct(
        protected HotelRoomService $rooms,
        protected HotelCustomFieldService $customFields,
    ) {}

    public function index(Property $property): Response
    {
        $property->load(['roomTypes' => fn ($q) => $q->withCount(['units', 'images'])->orderBy('sort_order')->orderBy('id')]);

        return Inertia::render('Admin/Hotel/RoomTypes/Index', [
            'property' => $property->only(['id', 'name', 'slug', 'status']),
            'roomTypes' => $property->roomTypes,
            'statuses' => collect(RoomTypeStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Property $property): Response
    {
        return Inertia::render('Admin/Hotel/RoomTypes/Form', $this->formData($property));
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate(HotelRoomService::roomTypeRules());
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues('room_type', $request->input('custom_fields', []));

        $roomType = DB::transaction(function () use ($property, $data, $custom): HotelRoomType {
            $roomType = $this->rooms->createRoomType($property, $data);
            $this->customFields->saveValues('room_type', $roomType->id, $custom);

            return $roomType;
        });

        return redirect(route('admin.hotel.room-types.edit', $roomType, absolute: false))
            ->with('flash', 'Room type created.');
    }

    public function show(HotelRoomType $roomType): Response
    {
        $roomType->load(['property:id,name,slug', 'bedTypes', 'amenities:id,name,category', 'images', 'units']);

        return Inertia::render('Admin/Hotel/RoomTypes/Show', ['roomType' => $roomType]);
    }

    public function edit(HotelRoomType $roomType): Response
    {
        return Inertia::render('Admin/Hotel/RoomTypes/Form', $this->formData($roomType->property, $roomType));
    }

    public function update(Request $request, HotelRoomType $roomType): RedirectResponse
    {
        $data = $request->validate(HotelRoomService::roomTypeRules($roomType));
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues('room_type', $request->input('custom_fields', []));

        DB::transaction(function () use ($roomType, $data, $custom): void {
            $this->rooms->updateRoomType($roomType, $data);
            $this->customFields->saveValues('room_type', $roomType->id, $custom);
        });

        return back()->with('flash', 'Room type updated.');
    }

    public function destroy(HotelRoomType $roomType): RedirectResponse
    {
        $propertyId = $roomType->property_id;
        $this->rooms->deleteRoomType($roomType);

        return redirect(route('admin.hotel.room-types.index', $propertyId, absolute: false))
            ->with('flash', 'Room type archived.');
    }

    public function storeImage(Request $request, HotelRoomType $roomType): RedirectResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:150'],
        ]);
        $this->rooms->addRoomImage($roomType, $data['image'], $data['alt_text'] ?? null);

        return back()->with('flash', 'Room image added.');
    }

    public function destroyImage(HotelRoomType $roomType, HotelRoomImage $image): RedirectResponse
    {
        $this->rooms->removeRoomImage($roomType, $image);

        return back()->with('flash', 'Room image removed.');
    }

    public function primaryImage(HotelRoomType $roomType, HotelRoomImage $image): RedirectResponse
    {
        $this->rooms->setPrimaryRoomImage($roomType, $image);

        return back()->with('flash', 'Cover image updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(Property $property, ?HotelRoomType $roomType = null): array
    {
        $roomType?->load(['bedTypes', 'amenities:id', 'images']);

        return [
            'property' => $property->only(['id', 'name', 'slug', 'status']),
            'roomType' => $roomType,
            'bedTypes' => HotelBedType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'amenities' => HotelAmenity::where('is_active', true)->whereIn('scope', ['room', 'both'])->orderBy('sort_order')->get(['id', 'name', 'category']),
            'statuses' => collect(RoomTypeStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
            'inventoryModes' => HotelRoomType::INVENTORY_MODES,
            'sizeUnits' => HotelRoomType::SIZE_UNITS,
            'showSize' => HotelSettings::enabled('hotel.rooms.show_size'),
            'showBeds' => HotelSettings::enabled('hotel.rooms.show_bed_details'),
            'unitsEnabled' => HotelSettings::enabled('hotel.rooms.units_enabled'),
            'customFields' => $roomType
                ? $this->customFields->formSchema('room_type', $roomType->id)
                : $this->customFields->blankSchema('room_type'),
        ];
    }
}
