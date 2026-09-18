<?php

namespace App\Models;

use App\Enums\TourModerationStatus;
use Database\Factories\TourPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourPackage extends Model
{
    /** @use HasFactory<TourPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'city_id', 'duration_days', 'duration_nights',
        'price', 'discounted_price', 'overview', 'day_wise_itinerary',
        'inclusions', 'exclusions', 'gallery', 'cover_image',
        'is_featured', 'is_active', 'category', 'category_id', 'meta_description', 'meta_title',
        'vendor_profile_id', 'created_by', 'moderation_status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note',
        'booking_enabled', 'available_weekdays', 'min_advance_days', 'max_advance_days',
    ];

    protected $casts = [
        'day_wise_itinerary' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'gallery' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'moderation_status' => TourModerationStatus::class,
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'booking_enabled' => 'boolean',
        'available_weekdays' => 'array',
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

    public function scopeApproved($query)
    {
        return $query->where('moderation_status', TourModerationStatus::Approved);
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('is_active', true)->where('moderation_status', TourModerationStatus::Approved);
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function moderationHistories(): HasMany
    {
        return $this->hasMany(TourModerationHistory::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(TourAddon::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeAddons(): HasMany
    {
        return $this->addons()->where('is_active', true);
    }

    public function blackoutDates(): HasMany
    {
        return $this->hasMany(TourBlackoutDate::class)->orderBy('date');
    }

    public function isOwnedByVendorProfile(?VendorProfile $profile): bool
    {
        return $profile && $this->vendor_profile_id && (int) $this->vendor_profile_id === (int) $profile->id;
    }

    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->discounted_price ?? $this->price);
    }
}
