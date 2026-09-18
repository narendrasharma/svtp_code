<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourBlackoutDate extends Model
{
    protected $fillable = [
        'tour_package_id',
        'date',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
