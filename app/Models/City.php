<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected $fillable = ['state_id', 'name', 'slug', 'is_spiritual_hub'];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function packages()
    {
        return $this->hasMany(TourPackage::class, 'city_id');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }
}
