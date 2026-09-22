<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable property type catalogue (12B.1): Hotel, Resort, Hostel, ...
 * Admin-managed; seeded with a generic global list.
 */
class PropertyType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function activeProperties(): HasMany
    {
        return $this->properties()->where('status', 'published');
    }
}
