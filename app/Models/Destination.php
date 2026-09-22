<?php

namespace App\Models;

use Database\Factories\DestinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    /** @use HasFactory<DestinationFactory> */
    use HasFactory;

    /**
     * Controlled discovery types. A destination is a travel concept
     * (city, area, region, island, tourism zone) — never a second City
     * identity. A city-level destination links via city_id; anything
     * broader or narrower uses destination_type + optional parent_id.
     */
    public const TYPE_CITY = 'city';

    public const TYPE_AREA = 'area';

    public const TYPE_REGION = 'region';

    public const TYPE_NEIGHBORHOOD = 'neighborhood';

    public const TYPE_ISLAND = 'island';

    public const TYPE_TOURISM_ZONE = 'tourism_zone';

    public const TYPE_OTHER = 'other';

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_CITY,
            self::TYPE_AREA,
            self::TYPE_REGION,
            self::TYPE_NEIGHBORHOOD,
            self::TYPE_ISLAND,
            self::TYPE_TOURISM_ZONE,
            self::TYPE_OTHER,
        ];
    }

    protected $fillable = [
        'country_id',
        'state_id',
        'city_id',
        'parent_id',
        'destination_type',
        'name',
        'slug',
        'description',
        'image',
        'latitude',
        'longitude',
        'meta_description',
        'meta_title',
        // -----------------------------------------------------------------
        // Added active flag – allows admin to mark a destination as active
        // -----------------------------------------------------------------
        'is_active',
        'is_featured',
        'sort_order',
    ];

    // -----------------------------------------------------------------
    // Cast is_active to boolean for consistent handling throughout the app
    // -----------------------------------------------------------------
    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Destination::class, 'parent_id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function tourPackages(): BelongsToMany
    {
        return $this->belongsToMany(TourPackage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
