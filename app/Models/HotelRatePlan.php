<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Commercial rate plan for a room type (12B.4).
 *
 * Meal plan and cancellation mode are commercial metadata only — no
 * restaurant or refund engines live here. Ownership always resolves
 * Room Type → Property → Vendor; no vendor id is stored or accepted.
 * Money is decimal(12,2); arithmetic uses bc strings at scale 2.
 */
class HotelRatePlan extends Model
{
    use HasFactory;

    public const MEAL_ROOM_ONLY = 'room_only';

    public const MEAL_BREAKFAST = 'breakfast';

    public const MEAL_HALF_BOARD = 'half_board';

    public const MEAL_FULL_BOARD = 'full_board';

    public const MEAL_ALL_INCLUSIVE = 'all_inclusive';

    public const MEAL_PLANS = [
        self::MEAL_ROOM_ONLY,
        self::MEAL_BREAKFAST,
        self::MEAL_HALF_BOARD,
        self::MEAL_FULL_BOARD,
        self::MEAL_ALL_INCLUSIVE,
    ];

    public const CANCEL_FLEXIBLE = 'flexible';

    public const CANCEL_NON_REFUNDABLE = 'non_refundable';

    public const CANCELLATION_MODES = [self::CANCEL_FLEXIBLE, self::CANCEL_NON_REFUNDABLE];

    protected $fillable = [
        'property_id', 'hotel_room_type_id',
        'name', 'code', 'description', 'currency',
        'meal_plan', 'cancellation_mode', 'cancellation_note',
        'base_adults', 'base_children',
        'base_rate', 'extra_adult_rate', 'extra_child_rate',
        'minimum_stay', 'maximum_stay',
        'is_active', 'sort_order', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'base_adults' => 'integer',
            'base_children' => 'integer',
            'base_rate' => 'decimal:2',
            'extra_adult_rate' => 'decimal:2',
            'extra_child_rate' => 'decimal:2',
            'minimum_stay' => 'integer',
            'maximum_stay' => 'integer',
            'is_active' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'hotel_room_type_id');
    }

    public function dailyRates(): HasMany
    {
        return $this->hasMany(HotelDailyRate::class, 'hotel_rate_plan_id');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(HotelRateSeason::class, 'hotel_rate_plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Plan covers every stay night. valid_until is the last sellable
     * night (inclusive); null bounds are unbounded.
     */
    public function coversDates(string $checkIn, string $lastNight): bool
    {
        if ($this->valid_from && $this->valid_from->toDateString() > $checkIn) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->toDateString() < $lastNight) {
            return false;
        }

        return true;
    }
}
