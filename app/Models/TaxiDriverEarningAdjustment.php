<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Signed earning adjustment (Phase 12A.10).
 *
 * Append-only: bonus/penalty/correction/toll reimbursement with a
 * required reason and actor. The parent earning's adjustments_total and
 * net_earning are refreshed by TaxiDriverEarningService — never by the
 * browser.
 */
class TaxiDriverEarningAdjustment extends Model
{
    use HasFactory;

    public const KINDS = ['bonus', 'penalty', 'correction', 'toll_reimbursement', 'misc'];

    protected $fillable = [
        'earning_id', 'amount', 'kind', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function earning(): BelongsTo
    {
        return $this->belongsTo(TaxiDriverEarning::class, 'earning_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
