<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared platform currency registry (Phase 13B).
 *
 * Owns DISPLAY metadata + the default display currency. Domain
 * authoritative prices (Property.currency, Tour INR, Taxi currency)
 * are untouched — conversion is presentational unless a transaction
 * flow explicitly says otherwise.
 */
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    public const SYMBOL_BEFORE = 'before';

    public const SYMBOL_AFTER = 'after';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_digits',
        'symbol_position',
        'is_active',
        'is_default_display',
        'sort_order',
    ];

    protected $casts = [
        'decimal_digits' => 'integer',
        'is_active' => 'boolean',
        'is_default_display' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }
}
