<?php

namespace App\Models;

use App\Enums\VendorVerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vendor_application_id',
        'status',
        'country_code',
        'entity_type',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'review_note',
        'verified_at',
    ];

    protected $casts = [
        'status' => VendorVerificationStatus::class,
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(VendorApplication::class, 'vendor_application_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function isVerified(): bool
    {
        return $this->status === VendorVerificationStatus::Verified;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            VendorVerificationStatus::Pending,
            VendorVerificationStatus::UnderReview,
        ], true);
    }
}
