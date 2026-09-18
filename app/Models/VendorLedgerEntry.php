<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable vendor financial ledger (Phase 7).
 *
 * Append-only: rows are never updated or deleted. Amounts are always
 * positive decimals; direction gives the sign. The unique reference column
 * is the idempotency key (earning/reversal/hold/release/settle per source).
 * Balance is always DERIVED via VendorLedgerService — never stored.
 */
class VendorLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'booking_id',
        'booking_refund_id',
        'withdrawal_request_id',
        'type',
        'direction',
        'amount',
        'currency',
        'reference',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'type' => LedgerEntryType::class,
        'direction' => LedgerDirection::class,
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingRefund(): BelongsTo
    {
        return $this->belongsTo(BookingRefund::class, 'booking_refund_id');
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(VendorWithdrawalRequest::class, 'withdrawal_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCredit(): bool
    {
        return $this->direction === LedgerDirection::Credit;
    }
}
