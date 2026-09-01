<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourPackage extends Model
{
    protected $fillable = [
        'title', 'slug', 'city_id', 'duration_days', 'duration_nights',
        'price', 'discounted_price', 'overview', 'day_wise_itinerary',
        'inclusions', 'exclusions', 'gallery', 'cover_image',
        'is_featured', 'is_active', 'category', 'meta_description',
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
