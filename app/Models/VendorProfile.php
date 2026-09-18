<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'slug',
        'entity_type',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country_code',
        'postcode',
        'website',
        'business_description',
        'logo_path',
        'cover_path',
        'public_description',
        'public_phone',
        'public_email',
        'social_links',
        'storefront_enabled',
        'is_active',
        'approved_at',
        'vendor_verification_id',
        'verification_status',
        'vendor_plan_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
        'social_links' => 'array',
        'storefront_enabled' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(VendorVerification::class, 'vendor_verification_id');
    }

    /**
     * Bookings historically assigned to this vendor at booking time.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class)->latest();
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(VendorWithdrawalRequest::class)->latest();
    }

    /**
     * Active payout destination (one row per vendor; replacements reset
     * verification). HasOne for convenience — uniqueness is enforced.
     */
    public function payoutAccount(): HasOne
    {
        return $this->hasOne(VendorPayoutAccount::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(TourPackage::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VendorPlan::class, 'vendor_plan_id');
    }

    public function planAssignments(): HasMany
    {
        return $this->hasMany(VendorPlanAssignment::class)->latest();
    }

    /**
     * Public storefront visibility: approved + active + storefront on.
     * KYC verification is a badge, never a visibility gate.
     */
    public function isPubliclyVisible(): bool
    {
        return (bool) $this->is_active
            && $this->approved_at !== null
            && (bool) ($this->storefront_enabled ?? true);
    }

    public function publicDescription(): ?string
    {
        return $this->public_description ?: $this->business_description;
    }

    /**
     * KYC payout gate source of truth: resolves the LIVE verification row,
     * never the denormalized verification_status snapshot (which can go
     * stale when admin verifies KYC after approval).
     */
    public function isKycVerified(): bool
    {
        $verification = $this->verification()->first();

        return $verification !== null && $verification->isVerified();
    }
}
