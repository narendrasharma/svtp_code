<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced by BookingPolicy in the controller.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
