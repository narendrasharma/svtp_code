<?php

namespace App\Models;

use App\Enums\RoomTypeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sellable accommodation category within a property — Deluxe King,
 * Standard Twin, Family Suite, Dormitory Bed (12B.2).
 *
 * Structural capacity only (occupancy, size, beds, total_units or
 * physical units). Date inventory and pricing arrive in 12B.3/12B.4.
 * Ownership always resolves through the parent property.
 */
class HotelRoomType extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const INVENTORY_AGGREGATE = 'aggregate';

    public const INVENTORY_UNITS = 'units';

    public const INVENTORY_MODES = [self::INVENTORY_AGGREGATE, self::INVENTORY_UNITS];

    public const SIZE_UNITS = ['sqm', 'sqft'];

    protected $fillable = [
        'name', 'slug', 'short_description', 'description',
        'max_adults', 'max_children', 'max_occupancy',
        'base_adults', 'base_children',
        'size_value', 'size_unit', 'bed_summary',
        'inventory_mode', 'total_units',
        'status', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_occupancy' => 'integer',
            'base_adults' => 'integer',
            'base_children' => 'integer',
            'size_value' => 'decimal:2',
            'total_units' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function status(): RoomTypeStatus
    {
        return RoomTypeStatus::from((string) $this->getAttribute('status'));
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bedTypes(): BelongsToMany
    {
        return $this->belongsToMany(HotelBedType::class, 'hotel_room_type_beds', 'room_type_id', 'bed_type_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(HotelAmenity::class, 'hotel_room_type_amenities', 'room_type_id', 'hotel_amenity_id')
            ->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelRoomImage::class, 'room_type_id')->orderBy('sort_order')->orderBy('id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(HotelRoomUnit::class, 'room_type_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(HotelRoomInventory::class, 'hotel_room_type_id');
    }

    public function reservationNights(): HasManyThrough
    {
        return $this->hasManyThrough(HotelReservationNight::class, HotelBookingItem::class, 'room_type_id', 'hotel_booking_item_id', 'id', 'id');
    }

    public function ratePlans(): HasMany
    {
        return $this->hasMany(HotelRatePlan::class, 'hotel_room_type_id')->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): ?HotelRoomImage
    {
        return $this->images()->where('is_primary', true)->first() ?? $this->images()->first();
    }

    /**
     * Structural sellable capacity for 12B.3 to build on: the declared
     * total in aggregate mode, or the count of active physical units in
     * units mode. Never a date-specific number.
     */
    public function capacityUnits(): int
    {
        if ($this->inventory_mode === self::INVENTORY_UNITS) {
            return $this->units()->where('status', HotelRoomUnit::STATUS_ACTIVE)->count();
        }

        return (int) ($this->total_units ?? 0);
    }

    public function scopeActive($query)
    {
        return $query->where('status', RoomTypeStatus::Active->value);
    }
}
