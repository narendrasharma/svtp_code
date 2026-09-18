<?php

namespace App\Http\Requests;

use App\Models\TourAddon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTourAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isVendor() || false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pricing_type' => ['required', Rule::in(TourAddon::pricingTypes())],
            'price' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
