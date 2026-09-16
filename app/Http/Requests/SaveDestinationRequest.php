<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDestinationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('destinations')->ignore($this->route('destination'))],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'description' => ['nullable', 'string'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['boolean'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'meta_title' => [
                'nullable',
                'string',
                'max:70',
            ],
            // -----------------------------------------------------------------
            // Updated rule – always validate the active flag as a boolean.
            // This ensures the field is always present in the request payload,
            // even when the checkbox is unchecked (value will be false).
            // -----------------------------------------------------------------
            'is_active' => ['required', 'boolean'],
        ];
    }
}
