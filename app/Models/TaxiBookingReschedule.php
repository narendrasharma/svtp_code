<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxiBookingReschedule extends Model
{
    protected $fillable = ['taxi_booking_id', 'actor_id', 'old_pickup_at', 'old_return_at', 'new_pickup_at', 'new_return_at', 'reason', 'snapshot'];

    protected function casts(): array
    {
        return ['old_pickup_at' => 'datetime', 'old_return_at' => 'datetime', 'new_pickup_at' => 'datetime', 'new_return_at' => 'datetime', 'snapshot' => 'array'];
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
