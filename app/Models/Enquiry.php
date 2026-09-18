<?php

namespace App\Models;

use App\Services\LeadService;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use HasFactory;

    protected $fillable = [
        'enquiry_type',
        'status',
        'tour_package_id',
        'full_name',
        'phone',
        'email',
        'pickup_drop',
        'hotel_category',
        'adults',
        'children',
        'arrival_date',
        'departure_date',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'arrival_date' => 'date',
            'departure_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Compatibility bridge (Phase 11.5B): every website enquiry feeds
        // the CRM pipeline as a lead. The enquiry itself is untouched —
        // forms, math CAPTCHA and the admin Enquiries page keep working.
        // fromEnquiry() is idempotent per enquiry.
        static::created(function (Enquiry $enquiry): void {
            try {
                app(LeadService::class)->fromEnquiry($enquiry);
            } catch (\Throwable $e) {
                // A CRM bridge failure must never break the public form.
                report($e);
            }
        });
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }
}
