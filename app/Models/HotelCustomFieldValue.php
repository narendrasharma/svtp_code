<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One typed value per (definition, entity). Value column stores scalars
 * as plain strings and multiselects as JSON arrays; the definition's
 * field_type decides decoding. No per-field SQL columns.
 */
class HotelCustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = ['definition_id', 'entity_type', 'entity_id', 'value'];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(HotelCustomFieldDefinition::class, 'definition_id');
    }

    /**
     * @return string|array<int, string>|bool|null
     */
    public function typed()
    {
        $type = $this->definition?->field_type;
        $raw = $this->value;

        if ($raw === null) {
            return null;
        }

        return match ($type) {
            HotelCustomFieldDefinition::TYPE_MULTISELECT => array_values(array_filter((array) (json_decode((string) $raw, true) ?? []), 'is_string')),
            HotelCustomFieldDefinition::TYPE_BOOLEAN => in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true),
            default => (string) $raw,
        };
    }

    public function display(): ?string
    {
        $typed = $this->typed();

        if ($typed === null || $typed === '' || $typed === []) {
            return null;
        }

        $definition = $this->definition;

        if (is_bool($typed)) {
            return $typed ? 'Yes' : 'No';
        }

        if (is_array($typed) && $definition) {
            $labels = array_map(fn (string $value): string => $definition->labelFor($value), $typed);

            return implode(', ', $labels);
        }

        if (is_string($typed) && $definition && $definition->field_type === HotelCustomFieldDefinition::TYPE_SELECT) {
            return $definition->labelFor($typed);
        }

        return is_string($typed) ? $typed : null;
    }
}
