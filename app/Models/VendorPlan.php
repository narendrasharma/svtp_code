<?php

namespace App\Models;

use Database\Factories\VendorPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPlan extends Model
{
    /** @use HasFactory<VendorPlanFactory> */
    use HasFactory;

    public const KEY_MAX_ACTIVE_TOURS = 'max_active_tours';

    public const KEY_MAX_COUPONS = 'max_coupons';

    public const KEY_MAX_ADDONS_PER_TOUR = 'max_addons_per_tour';

    public const KEY_FEATURED_LISTING = 'featured_listing';

    public const KEY_STOREFRONT_ENABLED = 'storefront_enabled';

    public const KEY_ANALYTICS_LEVEL = 'analytics_level';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(VendorPlanFeature::class)->orderBy('key');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VendorPlanAssignment::class);
    }

    public function feature(string $key): ?VendorPlanFeature
    {
        return $this->features->firstWhere('key', $key);
    }

    /**
     * @return array<int, string>
     */
    public static function supportedKeys(): array
    {
        return [
            self::KEY_MAX_ACTIVE_TOURS,
            self::KEY_MAX_COUPONS,
            self::KEY_MAX_ADDONS_PER_TOUR,
            self::KEY_FEATURED_LISTING,
            self::KEY_STOREFRONT_ENABLED,
            self::KEY_ANALYTICS_LEVEL,
        ];
    }
}
