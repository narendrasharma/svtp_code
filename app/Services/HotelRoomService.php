<?php

namespace App\Services;

use App\Enums\RoomTypeStatus;
use App\Models\HotelAmenity;
use App\Models\HotelBedType;
use App\Models\HotelRoomImage;
use App\Models\HotelRoomType;
use App\Models\HotelRoomUnit;
use App\Models\Property;
use App\Support\HotelHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Hotel room-domain service (12B.2).
 *
 * Room types, bed configuration, room amenities, room gallery and
 * optional physical units. Ownership always arrives as an already-scoped
 * Property — this service never trusts property/vendor IDs from input.
 * No pricing, inventory dates or bookings here (12B.3/12B.4+).
 */
class HotelRoomService
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function roomTypeRules(?HotelRoomType $roomType = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:20', 'lte:max_occupancy'],
            'max_children' => ['required', 'integer', 'min:0', 'max:20', 'lte:max_occupancy'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:30'],
            'base_adults' => ['nullable', 'integer', 'min:1', 'lte:max_adults'],
            'base_children' => ['nullable', 'integer', 'min:0', 'lte:max_children'],
            'size_value' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'size_unit' => ['nullable', 'string', Rule::in(HotelRoomType::SIZE_UNITS)],
            'inventory_mode' => ['required', 'string', Rule::in(HotelRoomType::INVENTORY_MODES)],
            'total_units' => ['nullable', 'integer', 'min:1', 'max:5000', 'required_if:inventory_mode,aggregate'],
            'status' => ['sometimes', 'string', Rule::in(RoomTypeStatus::values())],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'beds' => ['nullable', 'array', 'max:10'],
            'beds.*.bed_type_id' => ['required', 'integer', 'exists:hotel_bed_types,id'],
            'beds.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
            'amenity_ids' => ['nullable', 'array', 'max:50'],
            'amenity_ids.*' => ['integer', 'exists:hotel_amenities,id'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function roomUnitRules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:hotel_room_types,id'],
            'unit_name' => ['required', 'string', 'max:80'],
            'floor' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(HotelRoomUnit::STATUSES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated content fields only
     */
    public function createRoomType(Property $property, array $data): HotelRoomType
    {
        return DB::transaction(function () use ($property, $data): HotelRoomType {
            $roomType = $property->roomTypes()->create([
                ...$this->roomTypeAttributes($data),
                'slug' => $this->uniqueRoomSlug($property, $data['slug'] ?? null, $data['name']),
                'status' => $data['status'] ?? RoomTypeStatus::Draft->value,
            ]);

            $this->syncBeds($roomType, $data['beds'] ?? []);
            $this->syncRoomAmenities($roomType, $data['amenity_ids'] ?? []);
            $this->refreshBedSummary($roomType);

            return $roomType->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated content fields only
     */
    public function updateRoomType(HotelRoomType $roomType, array $data): HotelRoomType
    {
        return DB::transaction(function () use ($roomType, $data): HotelRoomType {
            $roomType->fill($this->roomTypeAttributes($data));

            if (! empty($data['slug']) && $data['slug'] !== $roomType->getOriginal('slug')) {
                $roomType->slug = $this->uniqueRoomSlug($roomType->property, $data['slug'], $roomType->name, $roomType->id);
            }

            $roomType->save();

            if (array_key_exists('beds', $data)) {
                $this->syncBeds($roomType->fresh(), $data['beds'] ?? []);
            }

            if (array_key_exists('amenity_ids', $data)) {
                $this->syncRoomAmenities($roomType->fresh(), $data['amenity_ids'] ?? []);
            }

            $this->refreshBedSummary($roomType->fresh());

            return $roomType->fresh();
        });
    }

    public function deleteRoomType(HotelRoomType $roomType): void
    {
        DB::transaction(function () use ($roomType): void {
            foreach ($roomType->images()->get() as $image) {
                Storage::disk('public')->delete($image->path);
            }

            $roomType->delete();
        });
    }

    /**
     * @param  array<int, array{bed_type_id: mixed, quantity: mixed}>  $beds
     */
    public function syncBeds(HotelRoomType $roomType, array $beds): void
    {
        $activeIds = HotelBedType::where('is_active', true)->pluck('id')->all();
        $sync = [];

        foreach ($beds as $bed) {
            $bedTypeId = (int) ($bed['bed_type_id'] ?? 0);
            $quantity = (int) ($bed['quantity'] ?? 0);

            if (! in_array($bedTypeId, $activeIds, true)) {
                throw ValidationException::withMessages(['beds' => 'Unknown or inactive bed type.']);
            }

            if ($quantity < 1 || $quantity > 30) {
                throw ValidationException::withMessages(['beds' => 'Bed quantity must be between 1 and 30.']);
            }

            $sync[$bedTypeId] = ['quantity' => $quantity];
        }

        $roomType->bedTypes()->sync($sync);
    }

    /**
     * @param  array<int, mixed>  $amenityIds  room-scoped active amenities only
     */
    public function syncRoomAmenities(HotelRoomType $roomType, array $amenityIds): void
    {
        $ids = HotelAmenity::whereIn('id', array_map('intval', $amenityIds))
            ->where('is_active', true)
            ->whereIn('scope', ['room', 'both'])
            ->pluck('id')
            ->all();

        if (count($ids) !== count(array_unique(array_map('intval', $amenityIds)))) {
            throw ValidationException::withMessages(['amenity_ids' => 'Amenities must be active room amenities.']);
        }

        $roomType->amenities()->sync($ids);
    }

    public function refreshBedSummary(HotelRoomType $roomType): void
    {
        $parts = $roomType->bedTypes()->orderBy('hotel_bed_types.sort_order')->get()
            ->map(fn (HotelBedType $bed): string => $bed->pivot->quantity.' × '.$bed->name)
            ->all();

        $roomType->forceFill(['bed_summary' => $parts === [] ? null : implode(', ', $parts)])->save();
    }

    public function addRoomImage(HotelRoomType $roomType, UploadedFile $file, ?string $altText = null): HotelRoomImage
    {
        $path = $file->store('rooms/'.$roomType->id, 'public');

        $image = $roomType->images()->create([
            'path' => $path,
            'alt_text' => $altText !== null ? mb_substr(trim(strip_tags($altText)), 0, 150) : null,
            'sort_order' => (int) ($roomType->images()->max('sort_order') ?? -1) + 1,
            'is_primary' => $roomType->images()->count() === 0,
        ]);

        return $image->fresh();
    }

    public function removeRoomImage(HotelRoomType $roomType, HotelRoomImage $image): void
    {
        abort_unless((int) $image->room_type_id === (int) $roomType->id, 404);

        DB::transaction(function () use ($roomType, $image): void {
            Storage::disk('public')->delete($image->path);
            $wasPrimary = $image->is_primary;
            $image->delete();

            if ($wasPrimary) {
                $next = $roomType->images()->orderBy('sort_order')->first();

                if ($next) {
                    $next->forceFill(['is_primary' => true])->save();
                }
            }
        });
    }

    public function setPrimaryRoomImage(HotelRoomType $roomType, HotelRoomImage $image): HotelRoomImage
    {
        abort_unless((int) $image->room_type_id === (int) $roomType->id, 404);

        return DB::transaction(function () use ($roomType, $image): HotelRoomImage {
            $roomType->images()->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true])->save();

            return $image->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated unit fields only
     */
    public function createUnit(Property $property, array $data): HotelRoomUnit
    {
        $roomType = HotelRoomType::whereKey($data['room_type_id'])->firstOrFail();
        abort_unless((int) $roomType->property_id === (int) $property->id, 422, 'Room type belongs to another property.');

        $this->assertUnitNameFree($property, $data['unit_name']);

        return $property->roomUnits()->create([
            'room_type_id' => $roomType->id,
            'unit_name' => trim((string) $data['unit_name']),
            'floor' => isset($data['floor']) ? mb_substr(trim((string) $data['floor']), 0, 40) : null,
            'status' => $data['status'],
            'notes' => isset($data['notes']) ? mb_substr(trim(strip_tags((string) $data['notes'])), 0, 500) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  validated unit fields only
     */
    public function updateUnit(Property $property, HotelRoomUnit $unit, array $data): HotelRoomUnit
    {
        abort_unless((int) $unit->property_id === (int) $property->id, 404);

        $roomType = HotelRoomType::whereKey($data['room_type_id'])->firstOrFail();
        abort_unless((int) $roomType->property_id === (int) $property->id, 422, 'Room type belongs to another property.');

        if (strcasecmp(trim((string) $data['unit_name']), (string) $unit->unit_name) !== 0) {
            $this->assertUnitNameFree($property, $data['unit_name']);
        }

        $unit->update([
            'room_type_id' => $roomType->id,
            'unit_name' => trim((string) $data['unit_name']),
            'floor' => isset($data['floor']) ? mb_substr(trim((string) $data['floor']), 0, 40) : null,
            'status' => $data['status'],
            'notes' => isset($data['notes']) ? mb_substr(trim(strip_tags((string) $data['notes'])), 0, 500) : null,
        ]);

        return $unit->fresh();
    }

    public function deleteUnit(Property $property, HotelRoomUnit $unit): void
    {
        abort_unless((int) $unit->property_id === (int) $property->id, 404);

        $unit->delete();
    }

    protected function assertUnitNameFree(Property $property, string $unitName): void
    {
        $exists = HotelRoomUnit::where('property_id', $property->id)
            ->whereRaw('LOWER(unit_name) = ?', [mb_strtolower(trim($unitName))])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['unit_name' => 'This unit name already exists for the property.']);
        }
    }

    public function uniqueRoomSlug(Property $property, ?string $desired, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) ($desired !== null && trim($desired) !== '' ? $desired : $name)) ?: 'room');
        $base = mb_substr($base !== '' ? $base : 'room', 0, 150);
        $slug = $base;
        $counter = 2;

        while (HotelRoomType::where('property_id', $property->id)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->withTrashed()->exists()) {
            $slug = mb_substr($base, 0, 170).'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function roomTypeAttributes(array $data): array
    {
        $attributes = (new HotelRoomType)->getFillable();
        $out = [];

        foreach ($attributes as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }

        unset($out['slug'], $out['bed_summary']);

        if (array_key_exists('description', $out)) {
            $out['description'] = HotelHtml::clean($out['description'] !== null ? (string) $out['description'] : null);
        }

        if (array_key_exists('short_description', $out) && $out['short_description'] !== null) {
            $out['short_description'] = trim(strip_tags((string) $out['short_description']));
        }

        if (($out['inventory_mode'] ?? null) === HotelRoomType::INVENTORY_UNITS) {
            $out['total_units'] = null;
        }

        return $out;
    }
}
