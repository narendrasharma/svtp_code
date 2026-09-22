<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generic marketplace property — Hotel, Resort, Hostel, Guest House,
 * Homestay, Villa, Apartment, Lodge (12B.1).
 *
 * Vendor-owned (vendor_profile_id) or platform-managed (null).
 * Server-owned fields (ownership, status unless authorized, published_at)
 * are intentionally NOT all fillable — HotelPropertyService assigns them.
 */
class Property extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'property_type_id', 'name', 'slug',
        'short_description', 'description',
        'is_featured', 'star_rating',
        'address_line_1', 'address_line_2', 'city_id', 'state_id',
        'country_id', 'destination_id',
        'country_code', 'postal_code', 'latitude', 'longitude',
        'phone', 'email', 'website',
        'check_in_time', 'check_out_time', 'timezone', 'currency',
        'children_policy', 'pet_policy', 'smoking_policy',
        'check_in_instructions', 'house_rules',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'star_rating' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
            'published_at' => 'datetime',
            'reviews_count' => 'integer',
            'rating_average' => 'decimal:2',
        ];
    }

    public function status(): PropertyStatus
    {
        return PropertyStatus::from((string) $this->getAttribute('status'));
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(HotelAmenity::class, 'hotel_amenity_property')->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class)->orderBy('sort_order')->orderBy('id');
    }

    public function roomUnits(): HasMany
    {
        return $this->hasMany(HotelRoomUnit::class);
    }

    public function chargeRules(): HasMany
    {
        return $this->hasMany(HotelChargeRule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(HotelReview::class);
    }

    public function primaryImage(): ?PropertyImage
    {
        return $this->images()->where('is_primary', true)->first() ?? $this->images()->first();
    }

    public function scopePublished($query)
    {
        return $query->where('status', PropertyStatus::Published->value);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
