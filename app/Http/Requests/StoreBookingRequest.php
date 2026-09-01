<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'exists:tour_packages,id'],
            'travel_date' => ['required', 'date', 'after_or_equal:today'],
            'total_adults' => ['required', 'integer', 'min:1', 'max:30'],
            'total_children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'gateway' => ['nullable', 'in:razorpay,paytm'],
        ];
    }
}
