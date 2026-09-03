<?php

namespace App\Models;

use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    protected $fillable = ['destination_id', 'name', 'slug', 'description', 'image', 'meta_description'];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function tourPackages(): BelongsToMany
    {
        return $this->belongsToMany(TourPackage::class);
    }
}
