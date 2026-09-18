<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authoritative assignment trail. The booking keeps current foreign
 * keys for convenience; history rows are never mutated — reassignment
 * closes the open row and opens a new one.
 */
class TaxiAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxi_booking_id', 'driver_id', 'vehicle_id',
        'assigned_by', 'assigned_at', 'unassigned_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('unassigned_at');
    }
}
