<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vendor withdrawal request (Phase 7, manual settlement).
 *
 * Vendors request against available ledger balance; the request holds funds
 * immediately. Admins approve/reject/mark-paid. No gateway integration —
 * payout happens off-platform and is recorded with a reference.
 */
class VendorWithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'payout_account_id',
        'payout_method',
        'payout_destination_masked',
        'amount',
        'currency',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'rejection_reason',
        'paid_at',
        'payout_reference',
        'vendor_note',
    ];

    protected $casts = [
        'status' => WithdrawalStatus::class,
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Historical payout destination snapshot (masked only). Null for
     * pre-Phase-8 requests.
     */
    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(VendorPayoutAccount::class, 'payout_account_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class, 'withdrawal_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === WithdrawalStatus::Pending;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            WithdrawalStatus::Rejected,
            WithdrawalStatus::Paid,
            WithdrawalStatus::Cancelled,
        ], true);
    }
}
