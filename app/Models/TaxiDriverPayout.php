<?php

namespace App\Models;

use App\Enums\TaxiDriverPayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Driver payout batch (Phase 12A.10).
 *
 * Single currency per batch, server-calculated total, explicit
 * allocation rows per earning. Manual settlement only — paid is
 * recorded with an off-platform reference, never a gateway call.
 */
class TaxiDriverPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_number', 'driver_id', 'vendor_profile_id',
        'currency', 'amount', 'status',
        'payment_method', 'payment_reference', 'notes',
        'period_start', 'period_end',
        'created_by', 'paid_by', 'paid_at',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new \LogicException('Payouts must be cancelled, never deleted.');
        });
        static::updating(function (self $payout): void {
            if ($payout->getRawOriginal('status') === TaxiDriverPayoutStatus::Paid->value && $payout->isDirty()) {
                throw new \LogicException('Paid payouts cannot be changed.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TaxiDriverPayoutStatus::class,
            'amount' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function statusEnum(): TaxiDriverPayoutStatus
    {
        return $this->status instanceof TaxiDriverPayoutStatus
            ? $this->status
            : TaxiDriverPayoutStatus::from((string) $this->getAttribute('status'));
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaxiDriverPayoutItem::class, 'payout_id');
    }

    public function earnings()
    {
        return $this->belongsToMany(TaxiDriverEarning::class, 'taxi_driver_payout_items', 'payout_id', 'earning_id')
            ->withPivot('amount');
    }

    public function isTerminal(): bool
    {
        return in_array($this->statusEnum(), [
            TaxiDriverPayoutStatus::Paid,
            TaxiDriverPayoutStatus::Cancelled,
            TaxiDriverPayoutStatus::Failed,
        ], true);
    }
}
