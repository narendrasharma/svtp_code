<?php

namespace App\Models;

use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }
}
