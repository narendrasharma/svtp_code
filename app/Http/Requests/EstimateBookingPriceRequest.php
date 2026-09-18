<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstimateBookingPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Read-only price quote input. No totals are accepted — the response is
     * always recomputed from the current tour package.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', Rule::exists('tour_packages', 'id')->where('is_active', true)->where('moderation_status', 'approved')],
            'total_adults' => ['required', 'integer', 'min:1', 'max:30'],
            'total_children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'travel_date' => ['nullable', 'date'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required_with:addons', 'integer', 'exists:tour_addons,id'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    /**
     * @return array{addons: array<int, array{addon_id: int, quantity?: int}>, coupon_code: ?string}
     */
    public function extrasData(): array
    {
        $addons = [];

        foreach ((array) $this->input('addons', []) as $row) {
            if (! is_array($row) || ! isset($row['addon_id'])) {
                continue;
            }

            $addons[] = [
                'addon_id' => (int) $row['addon_id'],
                'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : 1,
            ];
        }

        $coupon = $this->input('coupon_code');

        return [
            'addons' => $addons,
            'coupon_code' => is_string($coupon) && trim($coupon) !== '' ? trim($coupon) : null,
        ];
    }
}
