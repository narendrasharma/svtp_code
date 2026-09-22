<?php

namespace App\Http\Controllers\Vendor\Hotel;

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
 * Vendor room type management for OWN properties (12B.2).
 * Every lookup resolves Property → vendor ownership first (404 otherwise);
 * room types are additionally verified to belong to that property.
 */
class RoomController extends Controller
{
    public function __construct(
        protected HotelRoomService $rooms,
        protected HotelCustomFieldService $customFields,
    ) {}

    protected function property(Request $request, int $propertyId): Property
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        return Property::whereKey($propertyId)->where('vendor_profile_id', $profile->id)->firstOrFail();
    }

    protected function scoped(Request $request, HotelRoomType $roomType): HotelRoomType
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);
        abort_unless(
            $roomType->property !== null && (int) $roomType->property->vendor_profile_id === (int) $profile->id,
            404
        );

        return $roomType;
    }

    public function index(Request $request, Property $property): Response
    {
        $property = $this->property($request, $property->id);
        $property->load(['roomTypes' => fn ($q) => $q->withCount(['units', 'images'])->orderBy('sort_order')->orderBy('id')]);

        return Inertia::render('Vendor/Hotel/RoomTypes/Index', [
            'property' => $property->only(['id', 'name', 'slug', 'status']),
            'roomTypes' => $property->roomTypes,
            'statuses' => collect(RoomTypeStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request, Property $property): Response
    {
        $property = $this->property($request, $property->id);

        return Inertia::render('Vendor/Hotel/RoomTypes/Form', $this->formData($property));
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        $property = $this->property($request, $property->id);
        $data = $request->validate(HotelRoomService::roomTypeRules());
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues('room_type', $request->input('custom_fields', []));

        $roomType = DB::transaction(function () use ($property, $data, $custom): HotelRoomType {
            $roomType = $this->rooms->createRoomType($property, $data);
            $this->customFields->saveValues('room_type', $roomType->id, $custom);

            return $roomType;
        });

        return redirect(route('vendor.hotel.room-types.edit', $roomType, absolute: false))
            ->with('flash', 'Room type created.');
    }

    public function show(Request $request, HotelRoomType $roomType): Response
    {
        $roomType = $this->scoped($request, $roomType);
        $roomType->load(['property:id,name,slug', 'bedTypes', 'amenities:id,name,category', 'images', 'units']);

        return Inertia::render('Vendor/Hotel/RoomTypes/Show', ['roomType' => $roomType]);
    }

    public function edit(Request $request, HotelRoomType $roomType): Response
    {
        $roomType = $this->scoped($request, $roomType);

        return Inertia::render('Vendor/Hotel/RoomTypes/Form', $this->formData($roomType->property, $roomType));
    }

    public function update(Request $request, HotelRoomType $roomType): RedirectResponse
    {
        $roomType = $this->scoped($request, $roomType);
        $data = $request->validate(HotelRoomService::roomTypeRules($roomType));
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues('room_type', $request->input('custom_fields', []));

        DB::transaction(function () use ($roomType, $data, $custom): void {
            $this->rooms->updateRoomType($roomType, $data);
            $this->customFields->saveValues('room_type', $roomType->id, $custom);
        });

        return back()->with('flash', 'Room type updated.');
    }

    public function destroy(Request $request, HotelRoomType $roomType): RedirectResponse
    {
        $roomType = $this->scoped($request, $roomType);
        $propertyId = $roomType->property_id;
        $this->rooms->deleteRoomType($roomType);

        return redirect(route('vendor.hotel.room-types.index', $propertyId, absolute: false))
            ->with('flash', 'Room type archived.');
    }

    public function storeImage(Request $request, HotelRoomType $roomType): RedirectResponse
    {
        $roomType = $this->scoped($request, $roomType);
        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:150'],
        ]);
        $this->rooms->addRoomImage($roomType, $data['image'], $data['alt_text'] ?? null);

        return back()->with('flash', 'Room image added.');
    }

    public function destroyImage(Request $request, HotelRoomType $roomType, HotelRoomImage $image): RedirectResponse
    {
        $roomType = $this->scoped($request, $roomType);
        $this->rooms->removeRoomImage($roomType, $image);

        return back()->with('flash', 'Room image removed.');
    }

    public function primaryImage(Request $request, HotelRoomType $roomType, HotelRoomImage $image): RedirectResponse
    {
        $roomType = $this->scoped($request, $roomType);
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
