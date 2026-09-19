<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\PaymentStatus;
use App\Enums\TaxiBookingStatus;
use App\Enums\TripType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxiBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'customer_user_id', 'vendor_profile_id', 'lead_id', 'quotation_id',
        'trip_type', 'pickup_at', 'return_at', 'pickup_address', 'pickup_lat', 'pickup_lng',
        'drop_address', 'drop_lat', 'drop_lng',
        'airport_direction', 'flight_number', 'airline', 'terminal',
        'passenger_count', 'luggage_count',
        'vehicle_type_id', 'assigned_vehicle_id', 'assigned_driver_id',
        'customer_name', 'customer_phone', 'customer_email', 'special_instructions',
        'source', 'status', 'payment_status',
        'quoted_distance_km', 'quoted_duration_minutes',
        'payment_due_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'payment_due_date' => 'date',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_customer_reminder_at' => 'datetime',
            'last_vendor_reminder_at' => 'datetime',
            'base_amount' => 'decimal:2',
            'extra_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'pricing_snapshot' => 'array',
            'priced_at' => 'datetime',
        ];
    }

    public function status(): TaxiBookingStatus
    {
        return TaxiBookingStatus::from((string) $this->getAttribute('status'));
    }

    public function tripType(): TripType
    {
        return TripType::from((string) $this->getAttribute('trip_type'));
    }

    public function paymentState(): PaymentStatus
    {
        return PaymentStatus::from((string) $this->getAttribute('payment_status'));
    }

    public function source(): BookingSource
    {
        return BookingSource::from((string) $this->getAttribute('source'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(TaxiRateCard::class, 'taxi_rate_card_id');
    }

    public function rentalPackage(): BelongsTo
    {
        return $this->belongsTo(TaxiRentalPackage::class, 'taxi_rental_package_id');
    }

    public function assignedVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'assigned_vehicle_id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TaxiBookingStop::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TaxiBookingStatusHistory::class)->latest();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaxiAssignment::class)->orderByDesc('assigned_at');
    }

    public function dispatchOffers(): HasMany
    {
        return $this->hasMany(TaxiDispatchOffer::class, 'taxi_booking_id')->orderByDesc('offered_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TaxiBookingNote::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TaxiPayment::class)->oldest();
    }

    public function currentAssignment(): ?TaxiAssignment
    {
        return $this->assignments()->whereNull('unassigned_at')->latest('assigned_at')->first();
    }
}
