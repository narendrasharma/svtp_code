<?php

namespace App\Models;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverEmploymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'vendor_profile_id', 'user_id',
        'first_name', 'last_name', 'phone', 'email', 'photo_path',
        'date_of_birth', 'address',
        'emergency_contact_name', 'emergency_contact_phone',
        'joining_date', 'driver_type',
        'availability_status', 'employment_status', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function availability(): DriverAvailabilityStatus
    {
        return DriverAvailabilityStatus::from($this->availability_status);
    }

    public function employment(): DriverEmploymentStatus
    {
        return DriverEmploymentStatus::from($this->employment_status);
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class)->latest();
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(DriverAvailability::class)->orderBy('from_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaxiAssignment::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    public function isAssignable(): bool
    {
        return $this->is_active
            && $this->employment_status === DriverEmploymentStatus::Active->value
            && $this->availability_status === DriverAvailabilityStatus::Available->value;
    }

    /**
     * True when an unavailable/on-leave window covers the pickup.
     */
    public function isOnLeaveAt(\DateTimeInterface $pickupAt): bool
    {
        return $this->availabilities()
            ->whereIn('status', ['unavailable', 'on_leave'])
            ->where(function ($query) use ($pickupAt): void {
                $query->where(function ($q) use ($pickupAt): void {
                    $q->whereNotNull('from_at')->whereNotNull('to_at')
                        ->where('from_at', '<=', $pickupAt)
                        ->where('to_at', '>=', $pickupAt);
                })->orWhere(function ($q) use ($pickupAt): void {
                    $q->whereNotNull('date')->whereDate('date', '=', $pickupAt);
                });
            })
            ->exists();
    }
}
