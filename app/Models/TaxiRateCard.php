<?php

namespace App\Models;

use App\Enums\TripType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxiRateCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_profile_id', 'vehicle_type_id', 'name', 'trip_type',
        'currency', 'is_active', 'effective_from', 'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'trip_type' => TripType::class,
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TaxiRateRule::class);
    }

    public function rentalPackages(): HasMany
    {
        return $this->hasMany(TaxiRentalPackage::class)->orderBy('sort_order')->orderBy('name');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TaxiBooking::class);
    }

    public function scopeEffective(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('is_active', true)
            ->where(fn (Builder $builder): Builder => $builder->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn (Builder $builder): Builder => $builder->whereNull('effective_until')->orWhere('effective_until', '>=', $at));
    }
}
