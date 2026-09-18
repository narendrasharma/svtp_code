<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Services\BookingReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    /**
     * Tour-first booking record.
     *
     * The direct package_id relation is deliberate: tours are the only
     * product today. The product_type discriminator partitions future
     * Taxi/Hotel modules, which will arrive as sibling services (and, if
     * ever needed, a bookable morph populated from product_type) without
     * rewriting this model. Tour-specific pricing/travel logic lives in
     * TourBookingPricingService and BookingService — not here.
     */
    protected $fillable = [
        'product_type', 'source', 'user_id', 'package_id',
        'customer_name', 'customer_phone', 'customer_email', 'country',
        'pickup_address', 'special_requests',
        'travel_date', 'total_adults', 'total_children',
        'currency', 'base_price', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'payment_gateway', 'payment_reference', 'payment_status', 'booking_status',
        // Phase 11.5D: staff-set operational due date (validated in the
        // controller). Reminder markers stay guarded — services stamp
        // them via forceFill only.
        'payment_due_date',
        // NOTE: vendor_profile_id + financial snapshot columns are
        // intentionally NOT fillable. BookingService sets them via forceFill
        // from server-computed values; no client input may assign them.
    ];

    protected $casts = [
        'travel_date' => 'date',
        'booking_status' => BookingStatus::class,
        'payment_status' => PaymentStatus::class,
        'source' => BookingSource::class,
        // Phase 6 financial snapshot: decimal casts keep reads as exact
        // 2-decimal strings on every driver (SQLite NUMERIC affinity would
        // otherwise return ints). Null percentage (admin-owned tours) stays
        // null — decimal is a primitive cast type with a null early-return.
        'gross_amount' => 'decimal:2',
        'platform_commission_percentage' => 'decimal:2',
        'platform_commission_amount' => 'decimal:2',
        'vendor_earning_amount' => 'decimal:2',
        // Phase 10 coupon/add-on snapshots (immutable, forceFill only).
        'coupon_discount_value' => 'decimal:2',
        'addons_total' => 'decimal:2',
        // Phase 11.5B quotation trace (forceFill only on conversion).
        'quoted_total_amount' => 'decimal:2',
        // Phase 11.5D operational reminders.
        'payment_due_date' => 'date',
        'last_payment_reminder_at' => 'datetime',
        'last_travel_reminder_at' => 'datetime',
        'last_vendor_travel_reminder_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (Booking $booking) {
            // Safety net so direct creates always carry neutral identifiers.
            // BookingService sets these explicitly; never accept them from clients.
            $booking->booking_reference_id ??= BookingReference::generate();
            $booking->qr_code_string ??= Str::uuid()->toString();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'package_id');
    }

    /**
     * Historical vendor assignment snapshotted at booking time. Null means
     * an admin-owned tour — the platform retains the full booking value.
     */
    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->latest();
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(BookingCancellationRequest::class)->latest();
    }

    /**
     * Append-only financial ledger rows for this booking (earning credit,
     * refund reversal). The booking's own snapshot columns stay immutable.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class)->oldest();
    }

    /**
     * Immutable add-on line snapshot (Phase 10). Never recomputed from
     * live TourAddon rows.
     */
    public function bookingAddons(): HasMany
    {
        return $this->hasMany(BookingAddon::class)->oldest();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponRedemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    /**
     * Manual accounting refund records (Phase 8, processed only moves money
     * via linked reversal entries).
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class)->oldest();
    }

    /**
     * Manual/partial payment collections (Phase 11.5B). Append-only; the
     * outstanding balance is always derived, never stored.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->oldest();
    }

    /**
     * Date-change history (Phase 11.5B). Old dates are preserved here;
     * the booking row only ever carries the current travel date.
     */
    public function reschedules(): HasMany
    {
        return $this->hasMany(BookingReschedule::class)->oldest();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(BookingNote::class)->latest();
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function isTour(): bool
    {
        return $this->product_type === 'tour';
    }

    public function isVendorBooking(): bool
    {
        return $this->vendor_profile_id !== null;
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopeAssignedToVendor($query, int $vendorProfileId)
    {
        return $query->where('vendor_profile_id', $vendorProfileId);
    }

    public function guestCount(): int
    {
        return (int) $this->total_adults + (int) $this->total_children;
    }
}
