<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
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
        $couponId = $this->route('coupon')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($couponId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in([Coupon::TYPE_PERCENTAGE, Coupon::TYPE_FIXED])],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'minimum_booking_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0.01', 'max:10000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'scope' => ['required', Rule::in([Coupon::SCOPE_GLOBAL, Coupon::SCOPE_VENDOR, Coupon::SCOPE_TOURS])],
            'vendor_profile_id' => ['nullable', 'integer', 'exists:vendor_profiles,id'],
            'tour_ids' => ['nullable', 'array', 'max:100'],
            'tour_ids.*' => ['integer', 'exists:tour_packages,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => Coupon::normalizeCode((string) $this->input('code'))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('discount_type') === Coupon::TYPE_PERCENTAGE && (float) $this->input('discount_value', 0) > 100) {
                $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100.');
            }
        });
    }
}
