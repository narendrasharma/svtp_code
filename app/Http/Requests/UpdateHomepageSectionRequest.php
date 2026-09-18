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
            foreach (HomepageSectionService::validationRules($section->section_key) as $key => $keyRules) {
                $rules["settings.{$key}"] = array_merge(['sometimes'], $keyRules);
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
            $allowed = HomepageSectionService::allowedSettingKeys($section->section_key);
            foreach (array_keys($this->input('settings', []) ?? []) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add("settings.{$key}", 'This setting is not supported for this section.');
                }
            }
        });
    }
}
