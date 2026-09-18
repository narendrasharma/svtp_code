<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTourAvailabilityRequest extends FormRequest
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
            'booking_enabled' => ['required', 'boolean'],
            'available_weekdays' => ['nullable', 'array', 'max:7'],
            'available_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'min_advance_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_advance_days' => ['nullable', 'integer', 'min:1', 'max:730', 'gte:min_advance_days'],
        ];
    }
}
