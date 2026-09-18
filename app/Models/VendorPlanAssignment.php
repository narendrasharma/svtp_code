<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPlanAssignment extends Model
{
    protected $fillable = [
        'vendor_profile_id',
        'vendor_plan_id',
        'starts_at',
        'ends_at',
        'assigned_by',
        'note',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VendorPlan::class, 'vendor_plan_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
