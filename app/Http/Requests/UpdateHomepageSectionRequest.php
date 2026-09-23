<?php

namespace App\Http\Requests;

use App\Models\HomepageSection;
use App\Services\HomepageSectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateHomepageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];

        $section = $this->route('homepageSection');
        // Nested rules only apply when a settings object is actually sent, so
        // a bare visibility toggle never trips unrelated "required" rules.
        if ($section instanceof HomepageSection && is_array($this->input('settings'))) {
            $settingRules = HomepageSectionService::isSupportedType($section->section_type)
                ? HomepageSectionService::merchandisingValidationRules((string) $section->section_type)
                : HomepageSectionService::validationRules($section->section_key);

            foreach ($settingRules as $key => $keyRules) {
                $rules["settings.{$key}"] = array_merge(['sometimes'], $keyRules);
            }
        }

        if ($section instanceof HomepageSection && HomepageSectionService::isSupportedType($section->section_type)) {
            $definition = HomepageSectionService::merchandisingDefinition((string) $section->section_type);
            $sourceModes = $definition['source_modes'] ?? [];

            if ($sourceModes !== []) {
                $rules['source_mode'] = ['sometimes', 'string', 'in:'.implode(',', $sourceModes)];
                $rules['item_limit'] = ['sometimes', 'integer', 'min:1', 'max:24'];
                $rules['items'] = ['sometimes', 'array', 'max:24'];
                $rules['items.*'] = ['required', 'array:entity_type,entity_id,sort_order'];
                $rules['items.*.entity_type'] = ['required', 'string', 'in:'.implode(',', HomepageSectionService::merchandisingEntityTypes((string) $section->section_type))];
                $rules['items.*.entity_id'] = ['required', 'integer', 'min:1'];
                $rules['items.*.sort_order'] = ['required', 'integer', 'min:0', 'max:999'];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $section = $this->route('homepageSection');
            if (! $section instanceof HomepageSection) {
                return;
            }

            if ($section->section_type !== null && ! HomepageSectionService::isSupportedType($section->section_type)) {
                $validator->errors()->add('section_type', 'This homepage section type is not supported.');

                return;
            }

            if (HomepageSectionService::isSupportedType($section->section_type)) {
                $type = (string) $section->section_type;
                $definition = HomepageSectionService::merchandisingDefinition($type);
                $sourceMode = (string) ($this->input('source_mode', $section->source_mode) ?? '');

                if ($definition['source_modes'] !== [] && ! in_array($sourceMode, $definition['source_modes'], true)) {
                    $validator->errors()->add('source_mode', 'This source mode is not supported for the section.');
                }

                $this->validateManualItems($validator, $type);
            }

            $allowed = HomepageSectionService::allowedSettingKeys($section->section_key);

            if (HomepageSectionService::isSupportedType($section->section_type)) {
                $allowed = HomepageSectionService::merchandisingSettingKeys((string) $section->section_type);
            }

            foreach (array_keys($this->input('settings', []) ?? []) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add("settings.{$key}", 'This setting is not supported for this section.');
                }
            }
        });
    }

    private function validateManualItems(Validator $validator, string $type): void
    {
        if (! is_array($this->input('items'))) {
            return;
        }

        $allowedTypes = HomepageSectionService::merchandisingEntityTypes($type);
        $seen = [];

        foreach ($this->input('items') as $index => $item) {
            if (! is_array($item) || ! isset($item['entity_type'], $item['entity_id'])) {
                continue;
            }

            $entityType = (string) $item['entity_type'];
            $entityId = (int) $item['entity_id'];

            if (! in_array($entityType, $allowedTypes, true)) {
                continue;
            }

            $key = $entityType.':'.$entityId;
            if (isset($seen[$key])) {
                $validator->errors()->add("items.{$index}.entity_id", 'The same item may only be selected once.');

                continue;
            }
            $seen[$key] = true;

            $modelClass = HomepageSectionService::manualEntityModels()[$entityType] ?? null;
            if ($modelClass === null || ! $modelClass::query()->whereKey($entityId)->exists()) {
                $validator->errors()->add("items.{$index}.entity_id", 'The selected item does not exist.');
            }
        }
    }
}
