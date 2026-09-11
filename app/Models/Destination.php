<?php

namespace App\Models;

use Database\Factories\DestinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    /** @use HasFactory<DestinationFactory> */
    use HasFactory;

    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'description',
        'image',
        'meta_description',
        'meta_title',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function tourPackages(): BelongsToMany
    {
        return $this->belongsToMany(TourPackage::class);
    }
}
