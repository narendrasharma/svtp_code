<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxiCancellationPolicy extends Model
{
    protected $fillable = ['name', 'vendor_profile_id', 'trip_type', 'currency', 'is_active', 'effective_from', 'effective_until', 'free_cancel_before_minutes', 'fee_type', 'fee_value', 'no_show_fee_type', 'no_show_fee_value', 'minimum_fee', 'maximum_fee'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'effective_from' => 'datetime', 'effective_until' => 'datetime', 'fee_value' => 'decimal:2', 'no_show_fee_value' => 'decimal:2', 'minimum_fee' => 'decimal:2', 'maximum_fee' => 'decimal:2'];
    }
}
