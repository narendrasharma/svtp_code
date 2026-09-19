<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxiRefund extends Model
{
    protected $fillable = ['refund_number', 'taxi_booking_id', 'vendor_profile_id', 'request_key', 'currency', 'amount', 'status', 'method', 'reference', 'reason', 'calculation_snapshot', 'requested_by', 'processed_by', 'refunded_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'calculation_snapshot' => 'array', 'refunded_at' => 'datetime'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaxiRefundItem::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $refund): void {
            if ($refund->getRawOriginal('status') === 'processed' || $refund->isDirty(['amount', 'currency', 'taxi_booking_id', 'vendor_profile_id', 'calculation_snapshot'])) {
                throw new \LogicException('Refund financial history is immutable.');
            }
        });
        static::deleting(function (): void {
            throw new \LogicException('Refunds cannot be deleted.');
        });
    }
}
