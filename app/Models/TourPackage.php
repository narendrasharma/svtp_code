<?php

namespace App\Models;

use Database\Factories\TourPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TourPackage extends Model
{
    /** @use HasFactory<TourPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'city_id', 'duration_days', 'duration_nights',
        'price', 'discounted_price', 'overview', 'day_wise_itinerary',
        'inclusions', 'exclusions', 'gallery', 'cover_image',
        'is_featured', 'is_active', 'category', 'category_id', 'meta_description',
    ];

    protected $casts = [
        'day_wise_itinerary' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'gallery' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class, 'category_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'package_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'package_id');
    }

    public function approvedReviews()
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class);
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->discounted_price ?? $this->price);
    }
}
