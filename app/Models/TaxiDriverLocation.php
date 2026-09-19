<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw driver location telemetry (Phase 12A.5).
 *
 * Append-only pings. Booking records stay authoritative — telemetry is
 * never used to derive trip state. Deliberately NOT registered in the
 * audit trail (per-ping audit would be noise) and purged by
 * ops:cleanup after the retention window.
 */
class TaxiDriverLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id', 'taxi_booking_id', 'taxi_assignment_id',
        'latitude', 'longitude', 'accuracy_meters', 'heading', 'speed_kmh',
        'captured_at', 'received_at', 'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'heading' => 'decimal:1',
            'speed_kmh' => 'decimal:2',
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TaxiAssignment::class, 'taxi_assignment_id');
    }

    /**
     * Provider-neutral map marker contract for future map integration.
     *
     * @return array{latitude:string, longitude:string, accuracy_meters:?int, captured_at:?string, received_at:?string}
     */
    public function toMapPoint(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy_meters' => $this->accuracy_meters,
            'captured_at' => $this->captured_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
        ];
    }
}
