<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', Rule::exists('tour_packages', 'id')->where('is_active', true)->where('moderation_status', 'approved')],
            'travel_date' => ['required', 'date', 'after_or_equal:today'],
            'total_adults' => ['required', 'integer', 'min:1', 'max:30'],
            'total_children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'gateway' => ['nullable', 'in:razorpay,paytm'],
            // Phase 10: promo + extras. Money is never accepted — only
            // identifiers/quantities; pricing is recomputed server-side.
            'coupon_code' => ['nullable', 'string', 'max:50'],
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

    /**
     * Customer snapshot mapped onto the BookingService contract.
     *
     * @return array{name: ?string, email: ?string, phone: ?string, country: ?string, pickup_address: ?string, special_requests: ?string}
     */
    public function customerData(): array
    {
        return [
            'name' => $this->input('customer_name'),
            'email' => $this->input('customer_email'),
            'phone' => $this->input('customer_phone'),
            'country' => $this->input('country'),
            'pickup_address' => $this->input('pickup_address'),
            'special_requests' => $this->input('special_requests'),
        ];
    }
}
