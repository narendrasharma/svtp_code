<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCountryRequest extends FormRequest
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
        $country = $this->route('country');

        return [
            'name' => ['required', 'string', 'max:255'],
            'iso2' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/', Rule::unique('countries', 'iso2')->ignore($country)],
            'iso3' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/', Rule::unique('countries', 'iso3')->ignore($country)],
            'phone_code' => ['nullable', 'string', 'max:8', 'regex:/^\+?[0-9 ]+$/'],
            'currency_code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
