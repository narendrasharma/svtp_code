<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_VENDOR = 'vendor';

    public const SCOPE_TOURS = 'tours';

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'minimum_booking_amount',
        'maximum_discount_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_limit_per_user',
        'scope',
        'vendor_profile_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tours(): BelongsToMany
    {
        return $this->belongsToMany(TourPackage::class, 'coupon_tour_package');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isPercentage(): bool
    {
        return $this->discount_type === self::TYPE_PERCENTAGE;
    }

    public function isVendorCoupon(): bool
    {
        return $this->vendor_profile_id !== null;
    }

    public function isOwnedByVendorProfile(?VendorProfile $profile): bool
    {
        return $profile !== null
            && $this->vendor_profile_id !== null
            && (int) $this->vendor_profile_id === (int) $profile->id;
    }
}
