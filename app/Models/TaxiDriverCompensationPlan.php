<?php

namespace App\Models;

use App\Enums\TaxiDriverEarningCalculation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Driver compensation plan (Phase 12A.10).
 *
 * Exactly one scope per row: a driver-specific row (driver_id set), a
 * vendor default (vendor_profile_id set, driver_id null) or the platform
 * default (both null). Resolution never merges fragments from multiple
 * plans — first match in driver → vendor → platform order wins.
 */
class TaxiDriverCompensationPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'vendor_profile_id', 'driver_id', 'vehicle_type_id',
        'currency', 'calculation_type',
        'fixed_amount', 'percentage', 'percentage_base',
        'per_km_amount', 'per_hour_amount', 'minimum_earning',
        'allowance_passthrough', 'no_show_amount',
        'is_active', 'effective_from', 'effective_until',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'calculation_type' => TaxiDriverEarningCalculation::class,
            'fixed_amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'per_km_amount' => 'decimal:2',
            'per_hour_amount' => 'decimal:2',
            'minimum_earning' => 'decimal:2',
            'no_show_amount' => 'decimal:2',
            'allowance_passthrough' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffective($query, ?\DateTimeInterface $at = null)
    {
        $at ??= now();

        return $query
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $at));
    }

    public function isPlatformDefault(): bool
    {
        return $this->vendor_profile_id === null && $this->driver_id === null;
    }

    public function isVendorDefault(): bool
    {
        return $this->vendor_profile_id !== null && $this->driver_id === null;
    }

    public function isDriverSpecific(): bool
    {
        return $this->driver_id !== null;
    }
}
