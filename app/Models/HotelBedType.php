<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Reusable bed type catalogue (12B.2): Single, Double, Queen, ...
 * Admin-managed; room types attach them with quantities.
 */
class HotelBedType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(HotelRoomType::class, 'hotel_room_type_beds', 'bed_type_id', 'room_type_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
