<?php

namespace App\Services;

use App\Models\HotelCustomFieldDefinition;
use App\Models\HotelCustomFieldValue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hotel custom field engine (12B.2.1).
 *
 * Single home for definition retrieval, server-side validation,
 * normalization, transactional saves and public serialization — shared
 * by Property and Room Type flows so controllers stay thin. Browsers
 * never supply definition metadata; IDs are re-resolved here.
 */
class HotelCustomFieldService
{
    /**
     * Active definitions applicable to an entity, ordered for forms.
     *
     * @return Collection<int, HotelCustomFieldDefinition>
     */
    public function applicableDefinitions(string $entityType, ?int $propertyTypeId = null)
    {
        $this->assertEntity($entityType);

        $query = HotelCustomFieldDefinition::where('entity_type', $entityType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($entityType === HotelCustomFieldDefinition::ENTITY_PROPERTY) {
            // No pivot rows = applies to every property type.
            $query->where(function ($q) use ($propertyTypeId): void {
                $q->whereNotExists(function ($exists): void {
                    $exists->select(DB::raw('1'))
                        ->from('hotel_custom_field_property_type')
                        ->whereColumn(
                            'hotel_custom_field_property_type.definition_id',
                            'hotel_custom_field_definitions.id'
                        );
                });

                if ($propertyTypeId !== null) {
                    $q->orWhereExists(function ($exists) use ($propertyTypeId): void {
                        $exists->select(DB::raw('1'))
                            ->from('hotel_custom_field_property_type')
                            ->whereColumn(
                                'hotel_custom_field_property_type.definition_id',
                                'hotel_custom_field_definitions.id'
                            )
                            ->where('hotel_custom_field_property_type.property_type_id', $propertyTypeId);
                    });
                }
            });
        }

        return $query->get();
    }

    /**
     * Frontend-safe form schema with current values (no internals).
     *
     * @return array<int, array<string, mixed>>
     */
    public function formSchema(string $entityType, int $entityId, ?int $propertyTypeId = null): array
    {
        $definitions = $this->applicableDefinitions($entityType, $propertyTypeId);
        $values = HotelCustomFieldValue::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereIn('definition_id', $definitions->pluck('id')->all())
            ->get()
            ->keyBy('definition_id');

        return $definitions->map(fn (HotelCustomFieldDefinition $definition): array => [
            'id' => $definition->id,
            'key' => $definition->key,
            'label' => $definition->name,
            'type' => $definition->field_type,
            'group' => $definition->group_name,
            'help' => $definition->help_text,
            'placeholder' => $definition->placeholder,
            'required' => (bool) $definition->is_required,
            'options' => $definition->isOptionType() ? $definition->normalizedOptions() : [],
            'value' => $this->formValue($definition, $values->get($definition->id)),
        ])->all();
    }

    /**
     * Blank schema for create forms (no entity yet).
     *
     * @return array<int, array<string, mixed>>
     */
    public function blankSchema(string $entityType, ?int $propertyTypeId = null): array
    {
        return $this->applicableDefinitions($entityType, $propertyTypeId)
            ->map(fn (HotelCustomFieldDefinition $definition): array => [
                'id' => $definition->id,
                'key' => $definition->key,
                'label' => $definition->name,
                'type' => $definition->field_type,
                'group' => $definition->group_name,
                'help' => $definition->help_text,
                'placeholder' => $definition->placeholder,
                'required' => (bool) $definition->is_required,
                'options' => $definition->isOptionType() ? $definition->normalizedOptions() : [],
                'value' => $definition->field_type === HotelCustomFieldDefinition::TYPE_MULTISELECT ? [] : null,
            ])->all();
    }

    /**
     * Validate raw `custom_fields[definitionId] => value` input against
     * applicable definitions. Unknown IDs are rejected, never ignored.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, ?string> definition_id => normalized stored value
     */
    public function validateValues(string $entityType, array $input, ?int $propertyTypeId = null): array
    {
        $definitions = $this->applicableDefinitions($entityType, $propertyTypeId);
        $known = $definitions->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach (array_keys($input) as $rawId) {
            if (! in_array((int) $rawId, $known, true)) {
                throw ValidationException::withMessages(['custom_fields' => 'Unknown custom field submitted.']);
            }
        }

        $normalized = [];

        foreach ($definitions as $definition) {
            $raw = $input[(string) $definition->id] ?? $input[$definition->id] ?? null;
            $value = $this->normalize($definition, $raw);

            if ($value === null && $definition->is_required) {
                throw ValidationException::withMessages(["custom_fields.{$definition->id}" => $definition->name.' is required.']);
            }

            $normalized[$definition->id] = $value;
        }

        return $normalized;
    }

    /**
     * Persist normalized values transactionally: upsert non-empty, drop
     * emptied rows so "no value" stays absent, not blank.
     *
     * @param  array<int, ?string>  $normalized
     */
    public function saveValues(string $entityType, int $entityId, array $normalized): void
    {
        DB::transaction(function () use ($entityType, $entityId, $normalized): void {
            foreach ($normalized as $definitionId => $value) {
                if ($value === null) {
                    HotelCustomFieldValue::where('definition_id', $definitionId)
                        ->where('entity_type', $entityType)
                        ->where('entity_id', $entityId)
                        ->delete();

                    continue;
                }

                HotelCustomFieldValue::updateOrCreate(
                    ['definition_id' => $definitionId, 'entity_type' => $entityType, 'entity_id' => $entityId],
                    ['value' => $value]
                );
            }
        });
    }

    /**
     * Public-safe grouped values: active + frontend-visible definitions
     * with non-empty display only. No IDs, rules or configuration leak.
     *
     * @return array<int, array{group: string, fields: array<int, array{label: string, value: string, type: string}>}>
     */
    public function publicValues(string $entityType, int $entityId): array
    {
        $definitions = HotelCustomFieldDefinition::where('entity_type', $entityType)
            ->where('is_active', true)
            ->where('show_on_frontend', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($definitions->isEmpty()) {
            return [];
        }

        $values = HotelCustomFieldValue::with('definition')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereIn('definition_id', $definitions->pluck('id')->all())
            ->get()
            ->keyBy('definition_id');

        $groups = [];

        // Iterate ordered definitions (not raw value rows) so groups and
        // fields always follow sort_order, id regardless of write order.
        foreach ($definitions as $definition) {
            $value = $values->get($definition->id);

            if (! $value) {
                continue;
            }

            $display = $value->display();

            if ($display === null) {
                continue;
            }

            $group = $definition->group_name ?: 'Additional Information';
            $groups[$group][] = [
                'label' => $definition->show_label ? $definition->name : '',
                'value' => $display,
                'type' => $definition->field_type,
            ];
        }

        $out = [];

        foreach ($groups as $group => $fields) {
            $out[] = ['group' => $group, 'fields' => $fields];
        }

        return $out;
    }

    protected function formValue(HotelCustomFieldDefinition $definition, ?HotelCustomFieldValue $value): mixed
    {
        if ($value === null) {
            return $definition->field_type === HotelCustomFieldDefinition::TYPE_MULTISELECT ? [] : null;
        }

        $typed = $value->typed();

        if ($definition->field_type === HotelCustomFieldDefinition::TYPE_BOOLEAN) {
            return (bool) $typed;
        }

        return $typed;
    }

    protected function normalize(HotelCustomFieldDefinition $definition, mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            $raw = trim($raw);

            if ($raw === '') {
                return null;
            }
        }

        if (is_array($raw) && $raw === []) {
            return null;
        }

        $fail = fn (): never => throw ValidationException::withMessages(
            ["custom_fields.{$definition->id}" => $definition->name.' is invalid.']
        );

        switch ($definition->field_type) {
            case HotelCustomFieldDefinition::TYPE_TEXT:
                $clean = trim(strip_tags((string) $raw));

                return $clean === '' || mb_strlen((string) $raw) > 500 ? ($clean === '' ? null : $fail()) : $clean;
            case HotelCustomFieldDefinition::TYPE_TEXTAREA:
                $clean = trim(strip_tags((string) $raw));

                return $clean === '' || mb_strlen((string) $raw) > 2000 ? ($clean === '' ? null : $fail()) : $clean;
            case HotelCustomFieldDefinition::TYPE_NUMBER:
                return is_numeric($raw) ? (string) $raw : $fail();
            case HotelCustomFieldDefinition::TYPE_BOOLEAN:
                $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $bool === null ? $fail() : ($bool ? '1' : '0');
            case HotelCustomFieldDefinition::TYPE_SELECT:
                $value = (string) $raw;

                return in_array($value, $definition->allowedValues(), true) ? $value : $fail();
            case HotelCustomFieldDefinition::TYPE_MULTISELECT:
                $values = is_array($raw) ? array_values($raw) : [$raw];
                $allowed = $definition->allowedValues();

                foreach ($values as $value) {
                    if (! is_string($value) && ! is_numeric($value)) {
                        $fail();
                    }

                    if (! in_array((string) $value, $allowed, true)) {
                        $fail();
                    }
                }

                return json_encode(array_values(array_map('strval', $values)));
            case HotelCustomFieldDefinition::TYPE_DATE:
                try {
                    return Carbon::parse((string) $raw)->format('Y-m-d');
                } catch (\Throwable) {
                    $fail();
                }

                return null;
            case HotelCustomFieldDefinition::TYPE_URL:
                $url = (string) $raw;

                if (preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $url)) {
                    $fail();
                }

                return filter_var($url, FILTER_VALIDATE_URL) && mb_strlen($url) <= 1000 ? $url : $fail();
            case HotelCustomFieldDefinition::TYPE_EMAIL:
                $email = (string) $raw;

                return filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 150 ? $email : $fail();
            default:
                $fail();
        }

        return null;
    }

    protected function assertEntity(string $entityType): void
    {
        if (! in_array($entityType, HotelCustomFieldDefinition::ENTITIES, true)) {
            throw ValidationException::withMessages(['entity_type' => 'Unsupported custom field entity.']);
        }
    }
}
