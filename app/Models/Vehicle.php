<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'vendor_profile_id', 'vehicle_type_id', 'name',
        'registration_number', 'make', 'model', 'year', 'color',
        'passenger_capacity', 'luggage_capacity', 'fuel_type', 'transmission',
        'is_air_conditioned', 'status', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_air_conditioned' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function status(): VehicleStatus
    {
        return VehicleStatus::from($this->status);
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class)->latest();
    }

    public function unavailablePeriods(): HasMany
    {
        return $this->hasMany(VehicleUnavailablePeriod::class)->orderBy('from_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaxiAssignment::class);
    }

    public function isAssignable(): bool
    {
        return $this->is_active && $this->status === VehicleStatus::Available->value;
    }

    /**
     * True when a blocking unavailable period covers the given pickup.
     */
    public function isBlockedAt(\DateTimeInterface $pickupAt): bool
    {
        return $this->unavailablePeriods()
            ->where('from_at', '<=', $pickupAt)
            ->where('to_at', '>=', $pickupAt)
            ->exists();
    }
}
