<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taxi money trail (decision B): append-only rows mirroring the tour
 * BookingPayment pattern. Outstanding is always derived
 * (total − paid), never stored. No shared-polymorphic refactor of
 * tour finance in this phase.
 */
class TaxiPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'taxi_booking_id', 'amount', 'currency',
        'payment_method', 'paid_at', 'received_by',
        'external_reference', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function method(): PaymentMethod
    {
        return PaymentMethod::from($this->payment_method);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TaxiBooking::class, 'taxi_booking_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
