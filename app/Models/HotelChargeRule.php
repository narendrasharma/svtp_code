<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Property-scoped tax/fee rule (12B.4). Exclusive charges are added on
 * top of the room subtotal. Percentage rules may alternatively be
 * flagged included_in_price (extracted for display, never added);
 * fixed included charges are rejected — no pretending.
 *
 * Calculation base: percentage → room subtotal; fixed_stay → once per
 * quote; fixed_night → × nights; fixed_room → × rooms;
 * fixed_room_night → × rooms × nights.
 */
class HotelChargeRule extends Model
{
    use HasFactory;

    public const TYPE_TAX = 'tax';

    public const TYPE_FEE = 'fee';

    public const TYPES = [self::TYPE_TAX, self::TYPE_FEE];

    public const CALC_PERCENTAGE = 'percentage';

    public const CALC_FIXED_STAY = 'fixed_stay';

    public const CALC_FIXED_NIGHT = 'fixed_night';

    public const CALC_FIXED_ROOM = 'fixed_room';

    public const CALC_FIXED_ROOM_NIGHT = 'fixed_room_night';

    public const CALCULATIONS = [
        self::CALC_PERCENTAGE,
        self::CALC_FIXED_STAY,
        self::CALC_FIXED_NIGHT,
        self::CALC_FIXED_ROOM,
        self::CALC_FIXED_ROOM_NIGHT,
    ];

    protected $fillable = [
        'property_id', 'name', 'charge_type', 'calculation',
        'value', 'currency', 'included_in_price', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'included_in_price' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
