<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxiRentalPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'taxi_rate_card_id', 'name', 'included_hours', 'included_km',
        'package_price', 'extra_km_rate', 'extra_hour_rate', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'included_hours' => 'decimal:2',
            'included_km' => 'decimal:2',
            'package_price' => 'decimal:2',
            'extra_km_rate' => 'decimal:4',
            'extra_hour_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(TaxiRateCard::class, 'taxi_rate_card_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TaxiBooking::class);
    }
}
