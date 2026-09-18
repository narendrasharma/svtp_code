<?php

namespace App\Models;

use App\Enums\VendorApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'business_name',
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
        'admin_note',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'consent_accepted_at',
        'consent_policy_version',
    ];

    protected $casts = [
        'status' => VendorApplicationStatus::class,
        'reviewed_at' => 'datetime',
        'consent_accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verification(): HasOne
    {
        return $this->hasOne(VendorVerification::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', VendorApplicationStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === VendorApplicationStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === VendorApplicationStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === VendorApplicationStatus::Rejected;
    }
}
