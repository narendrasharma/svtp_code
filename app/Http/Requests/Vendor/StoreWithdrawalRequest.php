<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Identity, status and payout state are server-derived — the
            // vendor supplies only the amount (and an optional note).
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'vendor_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
