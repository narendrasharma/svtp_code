<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxiRefundItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['taxi_refund_id', 'taxi_payment_id', 'amount', 'payment_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
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
