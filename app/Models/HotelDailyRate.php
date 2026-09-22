<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sparse per-date rate override for a rate plan (12B.4).
 *
 * Dates without a row inherit base rate + seasonal rules. Explicit
 * amount beats seasons; stop_sell / closed flags are plan-level and
 * independent of 12B.3 room-inventory stop-sell. No quantities here.
 */
class HotelDailyRate extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'hotel_rate_plan_id', 'rate_date',
        'amount_override', 'extra_adult_override', 'extra_child_override',
        'minimum_stay_override', 'maximum_stay_override',
        'stop_sell', 'closed_to_arrival', 'closed_to_departure',
        'note', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date:Y-m-d',
            'amount_override' => 'decimal:2',
            'extra_adult_override' => 'decimal:2',
            'extra_child_override' => 'decimal:2',
            'minimum_stay_override' => 'integer',
            'maximum_stay_override' => 'integer',
            'stop_sell' => 'boolean',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(HotelRatePlan::class, 'hotel_rate_plan_id');
    }

    public function isDefault(): bool
    {
        return $this->amount_override === null
            && $this->extra_adult_override === null
            && $this->extra_child_override === null
            && $this->minimum_stay_override === null
            && $this->maximum_stay_override === null
            && ! $this->stop_sell
            && ! $this->closed_to_arrival
            && ! $this->closed_to_departure
            && trim((string) $this->note) === '';
    }
}
