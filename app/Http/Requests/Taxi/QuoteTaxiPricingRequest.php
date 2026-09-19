<?php

namespace App\Http\Requests\Taxi;

use App\Enums\TripType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteTaxiPricingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_profile_id' => ['nullable', 'integer', 'exists:vendor_profiles,id'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'trip_type' => ['required', Rule::in(TripType::bookable())],
            'pickup_at' => ['required', 'date'],
            'return_at' => ['nullable', 'date', 'after:pickup_at'],
            'rental_package_id' => ['nullable', 'integer', 'exists:taxi_rental_packages,id'],
            'quoted_distance_km' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'quoted_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'waiting_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'toll_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'parking_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }
}
