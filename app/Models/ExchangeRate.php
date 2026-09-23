<?php

namespace App\Models;

use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Exchange-rate storage (Phase 13B).
 *
 * Normalized to the canonical FX base (currency.fx_base, default USD).
 * Rates are decimal strings at scale 10 — never float. Only
 * validated positive rates are ever stored.
 */
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'base_currency_code',
        'quote_currency_code',
        'rate',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];
}
