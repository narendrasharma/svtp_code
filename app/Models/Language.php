<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared platform language registry (Phase 13A).
 *
 * Module-independent: never gated behind Hotels / Tours / Taxi.
 * Exactly one active default language is enforced at write time.
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'locale',
        'name',
        'native_name',
        'is_active',
        'is_default',
        'is_rtl',
        'sort_order',
        'date_format',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_rtl' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function direction(): string
    {
        return $this->is_rtl ? 'rtl' : 'ltr';
    }
}
