<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxiBookingCancellation extends Model
{
    protected $fillable = ['taxi_booking_id', 'vendor_profile_id', 'policy_id', 'cancelled_by', 'requested_by_type', 'reason_code', 'reason_text', 'status', 'currency', 'cancellation_fee', 'refundable_amount', 'calculation_snapshot', 'cancelled_at'];

    protected function casts(): array
    {
        return ['calculation_snapshot' => 'array', 'cancellation_fee' => 'decimal:2', 'refundable_amount' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Historical records are immutable.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Historical records cannot be deleted.');
        });
    }
}
