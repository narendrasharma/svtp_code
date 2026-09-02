<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'user_id', 'package_id', 'booking_reference_id', 'travel_date',
        'customer_name', 'customer_phone', 'customer_email', 'pickup_address',
        'total_adults', 'total_children', 'total_amount', 'payment_gateway',
        'payment_reference', 'payment_status', 'booking_status', 'qr_code_string',
    ];

    protected $casts = [
        'travel_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function (Booking $booking) {
            $booking->booking_reference_id ??= 'SVTP-'.strtoupper(Str::random(8));
            $booking->qr_code_string ??= Str::uuid()->toString();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(TourPackage::class, 'package_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
