<?php

namespace App\Http\Requests\Vendor;

use App\Enums\EntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', Rule::enum(EntityType::class)],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:2000'],
            'consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'You must confirm that you are authorised to submit these documents.',
        ];
    }
}
