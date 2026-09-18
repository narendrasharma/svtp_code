<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isVendor();
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:2000'],
            // Phase 11 storefront presentation (public opt-in only).
            'public_description' => ['nullable', 'string', 'max:2000'],
            'public_phone' => ['nullable', 'string', 'max:30'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'social_links' => ['nullable', 'array', 'max:10'],
            'social_links.*' => ['nullable', 'url', 'max:500'],
            'storefront_enabled' => ['nullable', 'boolean'],
            'logo_upload' => ['nullable', 'image', 'max:2048'],
            'cover_upload' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }
}
