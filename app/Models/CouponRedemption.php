<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id',
        'booking_id',
        'user_id',
        'email',
        'discount_amount',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Guest identity key: authenticated user id wins, otherwise the
     * normalized booking email. Never IP-based.
     */
    public static function identityKey(?int $userId, ?string $email): string
    {
        if ($userId !== null) {
            return 'user:'.$userId;
        }

        return 'email:'.strtolower(trim((string) $email));
    }
}
