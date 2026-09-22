<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Admin-defined hotel custom field definition (12B.2.1).
 *
 * Long-tail descriptive metadata for properties and room types only.
 * Vendors fill values; they never manage definitions. Values link by
 * definition_id so label edits never destroy stored data.
 */
class HotelCustomFieldDefinition extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const ENTITY_PROPERTY = 'property';

    public const ENTITY_ROOM_TYPE = 'room_type';

    public const ENTITIES = [self::ENTITY_PROPERTY, self::ENTITY_ROOM_TYPE];

    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_NUMBER = 'number';

    public const TYPE_SELECT = 'select';

    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_DATE = 'date';

    public const TYPE_URL = 'url';

    public const TYPE_EMAIL = 'email';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_NUMBER,
        self::TYPE_SELECT,
        self::TYPE_MULTISELECT,
        self::TYPE_BOOLEAN,
        self::TYPE_DATE,
        self::TYPE_URL,
        self::TYPE_EMAIL,
    ];

    protected $fillable = [
        'entity_type', 'name', 'key', 'field_type',
        'group_name', 'help_text', 'placeholder',
        'is_required', 'is_active', 'show_on_frontend', 'show_label',
        'sort_order', 'options',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'show_on_frontend' => 'boolean',
            'show_label' => 'boolean',
            'options' => 'array',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(HotelCustomFieldValue::class, 'definition_id');
    }

    public function propertyTypes(): BelongsToMany
    {
        return $this->belongsToMany(PropertyType::class, 'hotel_custom_field_property_type', 'definition_id', 'property_type_id')
            ->withTimestamps();
    }

    public function isOptionType(): bool
    {
        return in_array($this->field_type, [self::TYPE_SELECT, self::TYPE_MULTISELECT], true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function normalizedOptions(): array
    {
        $options = $this->options ?? [];

        if (! is_array($options)) {
            return [];
        }

        // Accept [{value,label}] or ["a","b"] shapes.
        if (array_is_list($options) && isset($options[0]) && is_array($options[0])) {
            return array_values(array_filter(array_map(fn ($option): ?array => isset($option['value'])
                ? ['value' => (string) $option['value'], 'label' => (string) ($option['label'] ?? $option['value'])]
                : null, $options)));
        }

        return array_values(array_map(fn ($option): array => ['value' => (string) $option, 'label' => (string) $option], array_values($options)));
    }

    /**
     * @return array<int, string>
     */
    public function allowedValues(): array
    {
        return array_column($this->normalizedOptions(), 'value');
    }

    public function labelFor(string $value): string
    {
        foreach ($this->normalizedOptions() as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }
}
