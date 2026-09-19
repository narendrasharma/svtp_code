<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit payout → earning allocation (Phase 12A.10).
 *
 * The unique earning_id guarantees an earning can never sit in two
 * batches at once. Cancelling a draft payout deletes its items, which
 * releases the earnings back to payable. Paid allocations are never
 * deleted — corrections use earning adjustments.
 */
class TaxiDriverPayoutItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_id', 'earning_id', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(TaxiDriverPayout::class, 'payout_id');
    }

    public function earning(): BelongsTo
    {
        return $this->belongsTo(TaxiDriverEarning::class, 'earning_id');
    }
}
