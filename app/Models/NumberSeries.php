<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Human-readable reference counter per entity type (Phase 11.5A).
 *
 * Changing a series affects FUTURE references only — historical rows keep
 * whatever reference they were issued. See NumberSeriesService.
 */
class NumberSeries extends Model
{
    use HasFactory;

    public const RESET_NEVER = 'never';

    public const RESET_YEARLY = 'yearly';

    public const RESET_MONTHLY = 'monthly';

    protected $fillable = [
        'entity',
        'display_name',
        'prefix',
        'separator',
        'include_year',
        'include_month',
        'padding',
        'start_number',
        'next_number',
        'reset_cycle',
        'last_period',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'include_year' => 'boolean',
            'include_month' => 'boolean',
            'padding' => 'integer',
            'start_number' => 'integer',
            'next_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function resetCycles(): array
    {
        return [self::RESET_NEVER, self::RESET_YEARLY, self::RESET_MONTHLY];
    }
}
