<?php

namespace App\Models;

use App\Enums\TaxiDriverEarningCalculation;
use App\Enums\TaxiDriverEarningStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable driver earning per completed trip (Phase 12A.10).
 *
 * One row per booking (unique taxi_booking_id) owned by the driver who
 * held the final open assignment. gross_earning + cached
 * adjustments_total explain net_earning; paid_amount tracks payout
 * allocations. Rows are never recalculated after plan edits — the
 * calculation_snapshot is the permanent explanation.
 */
class TaxiDriverEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'earning_number', 'driver_id', 'vendor_profile_id',
        'taxi_booking_id', 'taxi_assignment_id',
        'currency', 'calculation_type', 'calculation_snapshot',
        'gross_earning', 'adjustments_total', 'net_earning', 'paid_amount',
        'status', 'earned_at', 'payable_at', 'paid_at',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $earning): void {
            if ($earning->isDirty(['driver_id', 'vendor_profile_id', 'taxi_booking_id', 'taxi_assignment_id', 'currency', 'calculation_type', 'calculation_snapshot', 'gross_earning', 'earned_at'])) {
                throw new \LogicException('Historical earning facts cannot be changed.');
            }
        });
        static::deleting(function (): void {
            throw new \LogicException('Earnings must be voided, never deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'calculation_type' => TaxiDriverEarningCalculation::class,
            'calculation_snapshot' => 'array',
            'gross_earning' => 'decimal:2',
            'adjustments_total' => 'decimal:2',
            'net_earning' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => TaxiDriverEarningStatus::class,
            'earned_at' => 'datetime',
            'payable_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TaxiAssignment::class, 'taxi_assignment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(TaxiDriverEarningAdjustment::class, 'earning_id')->latest();
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(TaxiDriverPayoutItem::class, 'earning_id');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [
            TaxiDriverEarningStatus::Pending->value,
            TaxiDriverEarningStatus::Payable->value,
            TaxiDriverEarningStatus::PartiallyPaid->value,
        ]);
    }

    public function scopePayable($query)
    {
        return $query->where(fn ($q) => $q->whereIn('status', [
            TaxiDriverEarningStatus::Payable->value,
            TaxiDriverEarningStatus::PartiallyPaid->value,
        ])->orWhere(fn ($due) => $due->where('status', TaxiDriverEarningStatus::Pending->value)
            ->whereNotNull('payable_at')->where('payable_at', '<=', now())))
            ->whereColumn('net_earning', '>', 'paid_amount');
    }

    public function unpaidRemainder(): string
    {
        return bcsub((string) $this->net_earning, (string) $this->paid_amount, 2);
    }
}
