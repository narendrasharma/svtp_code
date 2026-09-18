<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Manual accounting refund record (Phase 8).
 *
 * No gateway integration: recording a processed refund is an accounting
 * entry, and the UI says so explicitly. Each processed refund owns exactly
 * one vendor reversal ledger entry (linked both ways); retries resolve to
 * the same reversal via the refund id. Booking snapshots stay immutable.
 */
class BookingRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'amount',
        'currency',
        'status',
        'reason',
        'processed_by',
        'processed_at',
        'reference',
        'external_reference',
        'vendor_reversal_amount',
    ];

    protected $casts = [
        'status' => RefundStatus::class,
        'amount' => 'decimal:2',
        'vendor_reversal_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class, 'booking_refund_id');
    }

    public function isProcessed(): bool
    {
        return $this->status === RefundStatus::Processed;
    }
}
