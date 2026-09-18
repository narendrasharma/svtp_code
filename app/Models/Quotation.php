<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'revision_number', 'root_quotation_id',
        'lead_id', 'customer_user_id', 'service_type', 'status',
        'currency', 'valid_until',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'terms', 'internal_note', 'customer_note',
        'created_by', 'public_token',
        'accepted_at', 'rejected_at', 'converted_at', 'converted_booking_id',
        // Phase 11.5D idempotency marker (stamped by reminder jobs).
        'expiry_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
        ];
    }

    public function status(): QuotationStatus
    {
        return QuotationStatus::from($this->status);
    }

    public function serviceType(): ServiceType
    {
        return ServiceType::from($this->service_type);
    }

    /**
     * A quotation counts as expired while still open past its validity.
     * Terminal states are stored explicitly; this is the lazy read.
     */
    public function isExpired(): bool
    {
        return in_array($this->status, [QuotationStatus::Sent->value, QuotationStatus::Viewed->value], true)
            && $this->valid_until !== null
            && $this->valid_until->isPast();
    }

    public function root(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'root_quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'root_quotation_id')->orderBy('revision_number');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function displayReference(): string
    {
        return $this->revision_number > 1
            ? "{$this->reference} (Rev {$this->revision_number})"
            : $this->reference;
    }
}
