<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourAddon extends Model
{
    use HasFactory;

    public const PRICING_FIXED = 'fixed';

    public const PRICING_PER_PERSON = 'per_person';

    public const PRICING_PER_QUANTITY = 'per_quantity';

    protected $fillable = [
        'tour_package_id',
        'name',
        'description',
        'pricing_type',
        'price',
        'is_required',
        'is_active',
        'max_quantity',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    public function isOwnedByVendorProfile(?VendorProfile $profile): bool
    {
        return $this->tour !== null && $this->tour->isOwnedByVendorProfile($profile);
    }

    /**
     * @return array<int, string>
     */
    public static function pricingTypes(): array
    {
        return [self::PRICING_FIXED, self::PRICING_PER_PERSON, self::PRICING_PER_QUANTITY];
    }
}
