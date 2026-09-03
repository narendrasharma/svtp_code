<?php

namespace App\Models;

use Database\Factories\TourCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourCategory extends Model
{
    /** @use HasFactory<TourCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tourPackages(): HasMany
    {
        return $this->hasMany(TourPackage::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
