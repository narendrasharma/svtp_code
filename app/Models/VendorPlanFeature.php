<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPlanFeature extends Model
{
    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_STRING = 'string';

    public const TYPE_UNLIMITED = 'unlimited';

    protected $fillable = [
        'vendor_plan_id',
        'key',
        'label',
        'value_type',
        'integer_value',
        'boolean_value',
        'string_value',
    ];

    protected $casts = [
        'boolean_value' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VendorPlan::class, 'vendor_plan_id');
    }

    public function isUnlimited(): bool
    {
        return $this->value_type === self::TYPE_UNLIMITED;
    }

    /**
     * @return int|string|bool|null null = unlimited or missing
     */
    public function effectiveValue(): int|string|bool|null
    {
        if ($this->isUnlimited()) {
            return null;
        }

        return match ($this->value_type) {
            self::TYPE_INTEGER => $this->integer_value !== null ? (int) $this->integer_value : null,
            self::TYPE_BOOLEAN => $this->boolean_value !== null ? (bool) $this->boolean_value : null,
            self::TYPE_STRING => $this->string_value,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valueTypes(): array
    {
        return [self::TYPE_INTEGER, self::TYPE_BOOLEAN, self::TYPE_STRING, self::TYPE_UNLIMITED];
    }
}
