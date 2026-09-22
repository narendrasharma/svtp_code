<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Reusable hotel amenity catalogue (12B.1, property-level).
 * Admin-managed definitions; properties attach active ones.
 */
class HotelAmenity extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon', 'category', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'hotel_amenity_property')->withTimestamps();
    }
}
