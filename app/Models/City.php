<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = ['state_id', 'name', 'slug', 'is_spiritual_hub'];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function packages()
    {
        return $this->hasMany(TourPackage::class, 'city_id');
    }
}
