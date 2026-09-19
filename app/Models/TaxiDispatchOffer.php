<?php

namespace App\Models;

use App\Enums\TaxiDispatchOfferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Controlled auto-dispatch offer history (12A.8).
 *
 * One row per attempt — never rewritten except pending → terminal.
 * Deliberately NOT in the audit-trail registry; lifecycle events are
 * logged explicitly by the orchestrator to avoid update noise.
 */
class TaxiDispatchOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxi_booking_id', 'driver_id', 'vehicle_id', 'taxi_assignment_id',
        'rank', 'status', 'offered_at', 'expires_at', 'responded_at',
        'accepted_at', 'rejected_at', 'expired_at', 'response_reason',
        'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function status(): TaxiDispatchOfferStatus
    {
        return TaxiDispatchOfferStatus::from((string) $this->getAttribute('status'));
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

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TaxiAssignment::class, 'taxi_assignment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', TaxiDispatchOfferStatus::Pending->value);
    }

    public function scopeAttempted($query)
    {
        return $query->where('status', '!=', TaxiDispatchOfferStatus::Cancelled->value);
    }
}
