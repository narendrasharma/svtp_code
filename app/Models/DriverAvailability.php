<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Simple future-ready availability window (12A.1).
 *
 * Either a full-day `date` marker or a `from_at`/`to_at` range with a
 * status of available|unavailable|on_leave. No recurring shift calendar.
 */
class DriverAvailability extends Model
{
    protected $table = 'driver_availabilities';

    use HasFactory;

    protected $fillable = [
        'driver_id', 'date', 'from_at', 'to_at', 'status', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'from_at' => 'datetime',
            'to_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
