<?php

namespace App\Http\Requests\Vendor;

use App\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutAccountRequest extends FormRequest
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
            // Verification state is server-derived — the vendor supplies
            // only destination details. Secrets are never returned.
            'method' => ['required', Rule::enum(PayoutMethod::class)],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:24'],
            'ifsc' => ['nullable', 'string', 'max:11'],
            'upi_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{method: string, account_holder_name: string, bank_name?: ?string, account_number?: ?string, ifsc?: ?string, upi_id?: ?string}
     */
    public function payoutData(): array
    {
        return [
            'method' => $this->string('method')->toString(),
            'account_holder_name' => $this->string('account_holder_name')->toString(),
            'bank_name' => $this->input('bank_name'),
            'account_number' => $this->input('account_number'),
            'ifsc' => $this->input('ifsc'),
            'upi_id' => $this->input('upi_id'),
        ];
    }
}
