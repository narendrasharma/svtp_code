<?php

namespace App\Models;

use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vendor payout destination (Phase 8).
 *
 * One active row per vendor (unique vendor_profile_id); submitting new
 * details REPLACES the row and resets verification to pending. Sensitive
 * identifiers are encrypted at rest via the `encrypted` cast and NEVER
 * serialized: they sit in $hidden and all UI flows use the masked columns.
 * Historical withdrawals keep their own masked snapshot, so replacing
 * details never rewrites history.
 */
class VendorPayoutAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'method',
        'account_holder_name',
        'bank_name',
        'account_number',
        'account_number_last4',
        'ifsc',
        'upi_id',
        'upi_id_masked',
        // NOTE: status/verification columns are fillable for server-side
        // service writes only. Requests shape their own allowlisted payload
        // (StorePayoutAccountRequest::payoutData), so clients can never set
        // these — covered by the mass-assignment test.
        'status',
        'verified_at',
        'verified_by',
        'rejection_reason',
    ];

    protected $casts = [
        'method' => PayoutMethod::class,
        'status' => PayoutAccountStatus::class,
        'account_number' => 'encrypted',
        'upi_id' => 'encrypted',
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'account_number',
        'upi_id',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->status === PayoutAccountStatus::Verified;
    }

    /**
     * Masked destination for display and withdrawal snapshots.
     */
    public function maskedDestination(): string
    {
        if ($this->method === PayoutMethod::Bank) {
            return 'XXXXXXXX'.($this->account_number_last4 ?? 'XXXX');
        }

        return $this->upi_id_masked ?? 'UPI on file';
    }

    /**
     * Safe array for Inertia: masked only, never decrypted secrets.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method instanceof \BackedEnum ? $this->method->value : $this->method,
            'method_label' => $this->method instanceof PayoutMethod ? $this->method->label() : null,
            'account_holder_name' => $this->account_holder_name,
            'bank_name' => $this->bank_name,
            'masked_destination' => $this->maskedDestination(),
            'ifsc' => $this->ifsc,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof PayoutAccountStatus ? $this->status->label() : null,
            'rejection_reason' => $this->rejection_reason,
            'verified_at' => $this->verified_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
