<?php

namespace App\Http\Requests;

use App\Models\Destination;
use App\Services\LocationHierarchy;
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
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'parent_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'destination_type' => ['nullable', 'string', Rule::in(Destination::types())],
            'latitude' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['nullable', 'numeric', 'min:-180', 'max:180'],
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
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Cross-field hierarchy check: city/state/country consistency plus
     * parent validity (no self-parent, no cycles, no cross-country
     * parent). Nulls stay permissive — a region-level destination
     * without a city is valid.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $destination = $this->route('destination');

            LocationHierarchy::validateDestination(
                $this->input('country_id') !== null ? (int) $this->input('country_id') : null,
                $this->input('state_id') !== null ? (int) $this->input('state_id') : null,
                $this->input('city_id') !== null ? (int) $this->input('city_id') : null,
                $this->input('parent_id') !== null ? (int) $this->input('parent_id') : null,
                $destination ? (int) $destination->getKey() : null,
            );
        });
    }
}
