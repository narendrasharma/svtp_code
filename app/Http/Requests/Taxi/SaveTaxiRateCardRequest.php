<?php

namespace App\Http\Requests\Taxi;

use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Enums\TripType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaxiRateCardRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'trip_type' => ['required', Rule::in(TripType::bookable())],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'rules' => ['nullable', 'array'],
            'rules.*.code' => ['required', Rule::in(TaxiRateRuleCode::values()), 'distinct'],
            'rules.*.calculation_type' => ['required', Rule::in(TaxiRateCalculationType::values())],
            'rules.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999.9999'],
            'rules.*.included_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'rules.*.unit' => ['nullable', 'string', 'max:20'],
            'rules.*.configuration' => ['nullable', 'array'],
            'packages' => ['nullable', 'array', 'max:50'],
            'packages.*.id' => ['nullable', 'integer', 'exists:taxi_rental_packages,id'],
            'packages.*.name' => ['required', 'string', 'max:120'],
            'packages.*.included_hours' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'packages.*.included_km' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'packages.*.package_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'packages.*.extra_km_rate' => ['required', 'numeric', 'min:0', 'max:999999999.9999'],
            'packages.*.extra_hour_rate' => ['required', 'numeric', 'min:0', 'max:999999999.9999'],
            'packages.*.is_active' => ['required', 'boolean'],
            'packages.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
