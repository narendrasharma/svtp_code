<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlackoutDateRequest extends FormRequest
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
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
