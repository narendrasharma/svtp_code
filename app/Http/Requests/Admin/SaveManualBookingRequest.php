<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveManualBookingRequest extends FormRequest
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
            'package_id' => ['required', 'exists:tour_packages,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'travel_date' => ['required', 'date'],
            'total_adults' => ['required', 'integer', 'min:1', 'max:100'],
            'total_children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', 'in:pending,paid,failed'],
            'booking_status' => ['required', 'in:confirmed,completed,cancelled'],
        ];
    }
}
