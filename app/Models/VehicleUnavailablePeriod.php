<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleUnavailablePeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id', 'from_at', 'to_at', 'type', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'from_at' => 'datetime',
            'to_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
