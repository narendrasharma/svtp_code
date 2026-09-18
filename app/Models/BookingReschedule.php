<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingReschedule extends Model
{
    use HasFactory;

    public const TYPE_DATE_CHANGE = 'date_change';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'booking_id', 'change_type',
        'old_travel_date', 'new_travel_date',
        'old_total', 'new_total', 'price_difference',
        'reason', 'requested_by', 'approved_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'old_travel_date' => 'date',
            'new_travel_date' => 'date',
            'old_total' => 'decimal:2',
            'new_total' => 'decimal:2',
            'price_difference' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
