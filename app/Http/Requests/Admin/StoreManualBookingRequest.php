<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Manual (phone/WhatsApp/walk-in) tour bookings.
     *
     * Totals are deliberately NOT accepted here — BookingService prices
     * every booking server-side from the current tour package.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', Rule::exists('tour_packages', 'id')->where('is_active', true)],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'travel_date' => ['required', 'date'],
            'total_adults' => ['required', 'integer', 'min:1', 'max:100'],
            'total_children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'booking_status' => ['nullable', Rule::enum(BookingStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required_with:addons', 'integer', 'exists:tour_addons,id'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:30'],
            // Reservation Desk extensions: link an existing customer,
            // pick the channel, optionally collect on the spot.
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'source' => ['nullable', Rule::in(BookingSource::staffCreatable())],
            'initial_payment_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'initial_payment_method' => ['nullable', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'initial_payment_note' => ['nullable', 'string', 'max:255'],
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
