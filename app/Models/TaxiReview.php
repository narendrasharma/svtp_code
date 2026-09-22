<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Post-trip taxi review (Phase 12A.12).
 *
 * One row per completed booking (unique taxi_booking_id). Relations are
 * derived server-side from the booking + final assignment at submit
 * time — never from request input. Moderation changes status only;
 * customer rating/comment columns are never rewritten by moderators.
 */
class TaxiReview extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_HIDDEN,
    ];

    public const FLAG_REASONS = ['abusive', 'unrelated', 'personal_information', 'spam', 'other'];

    protected $fillable = [
        'taxi_booking_id', 'customer_user_id', 'vendor_profile_id', 'driver_id', 'vehicle_id',
        'overall_rating', 'driver_rating', 'vehicle_rating', 'service_rating',
        'punctuality_rating', 'cleanliness_rating', 'comment',
        'status', 'submitted_at', 'approved_at', 'rejected_at',
        'moderated_by', 'moderation_note',
        'vendor_reply', 'vendor_replied_at',
        'flagged_at', 'flagged_by', 'flag_reason', 'flag_note',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'driver_rating' => 'integer',
            'vehicle_rating' => 'integer',
            'service_rating' => 'integer',
            'punctuality_rating' => 'integer',
            'cleanliness_rating' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'vendor_replied_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isVisible(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new \LogicException('Taxi reviews use status states, never hard deletion.');
        });
    }
}
