<?php

namespace App\Http\Requests\Taxi;

use App\Enums\TaxiDriverEarningCalculation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Compensation plan validation (Phase 12A.10).
 *
 * Amounts are server-validated numerics; ownership (which vendor/driver
 * may be attached) is enforced by the calling controller, never here.
 */
class SaveTaxiCompensationPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'vendor_profile_id' => ['nullable', 'integer', 'exists:vendor_profiles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'calculation_type' => ['required', Rule::in([
                TaxiDriverEarningCalculation::Fixed->value,
                TaxiDriverEarningCalculation::PercentTotal->value,
                TaxiDriverEarningCalculation::PercentBase->value,
                TaxiDriverEarningCalculation::PerKm->value,
                TaxiDriverEarningCalculation::PerHour->value,
                TaxiDriverEarningCalculation::Hybrid->value,
            ])],
            'fixed_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentage_base' => ['nullable', Rule::in(['total', 'base'])],
            'per_km_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'per_hour_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'minimum_earning' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'allowance_passthrough' => ['nullable', 'boolean'],
            'no_show_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'is_active' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planData(): array
    {
        $validated = $this->validated();

        return [
            'name' => trim($validated['name']),
            'driver_id' => $validated['driver_id'] ?? null,
            'vehicle_type_id' => $validated['vehicle_type_id'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'calculation_type' => $validated['calculation_type'],
            'fixed_amount' => $validated['fixed_amount'] ?? 0,
            'percentage' => $validated['percentage'] ?? 0,
            'percentage_base' => $validated['percentage_base'] ?? 'total',
            'per_km_amount' => $validated['per_km_amount'] ?? 0,
            'per_hour_amount' => $validated['per_hour_amount'] ?? 0,
            'minimum_earning' => $validated['minimum_earning'] ?? 0,
            'allowance_passthrough' => (bool) ($validated['allowance_passthrough'] ?? false),
            'no_show_amount' => $validated['no_show_amount'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_until' => $validated['effective_until'] ?? null,
        ];
    }
}
