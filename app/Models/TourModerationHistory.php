<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourModerationHistory extends Model
{
    protected $fillable = [
        'tour_package_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
