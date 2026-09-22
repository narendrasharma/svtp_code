<?php

namespace App\Models;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_number', 'idempotency_key', 'user_id', 'vendor_profile_id', 'property_id',
        'property_name_snapshot', 'vendor_name_snapshot', 'status', 'payment_status', 'currency',
        'check_in', 'check_out', 'nights', 'rooms_count', 'adults', 'children', 'guest_name',
        'guest_email', 'guest_phone', 'special_requests', 'subtotal', 'taxes', 'fees', 'total',
        'amount_paid', 'pricing_snapshot', 'terms_accepted_at', 'booked_at', 'confirmed_at',
        'cancelled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['status' => HotelBookingStatus::class, 'payment_status' => HotelPaymentStatus::class, 'check_in' => 'date', 'check_out' => 'date', 'pricing_snapshot' => 'array', 'terms_accepted_at' => 'datetime', 'booked_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HotelBookingItem::class);
    }

    public function reservationNights(): HasManyThrough
    {
        return $this->hasManyThrough(HotelReservationNight::class, HotelBookingItem::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(HotelBookingCancellation::class);
    }

    public function hotelRefunds(): HasMany
    {
        return $this->hasMany(HotelBookingRefund::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(HotelBookingChange::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(HotelReview::class);
    }
}
