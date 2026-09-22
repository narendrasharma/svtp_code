<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Date-range seasonal pricing rule for a rate plan (12B.4).
 *
 * Winner-takes-all: for a given night at most ONE season applies —
 * highest priority wins, ties broken by lowest id. Explicit daily
 * overrides always beat seasons; seasons beat the plan base rate.
 * applicable_weekdays uses Carbon day numbers (0 = Sunday).
 */
class HotelRateSeason extends Model
{
    use HasFactory;

    public const ADJUST_FIXED_AMOUNT = 'fixed_amount';

    public const ADJUST_PERCENTAGE = 'percentage';

    public const ADJUST_FIXED_PRICE = 'fixed_price';

    public const ADJUSTMENT_TYPES = [
        self::ADJUST_FIXED_AMOUNT,
        self::ADJUST_PERCENTAGE,
        self::ADJUST_FIXED_PRICE,
    ];

    protected $fillable = [
        'hotel_rate_plan_id', 'name',
        'start_date', 'end_date',
        'adjustment_type', 'adjustment_value',
        'priority', 'applicable_weekdays', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'adjustment_value' => 'decimal:2',
            'priority' => 'integer',
            'applicable_weekdays' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(HotelRatePlan::class, 'hotel_rate_plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<int, int>
     */
    public function weekdays(): array
    {
        $days = $this->applicable_weekdays ?? [];

        if (! is_array($days) || $days === []) {
            return [0, 1, 2, 3, 4, 5, 6];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $days),
            fn (int $day): bool => $day >= 0 && $day <= 6
        )));
    }
}
