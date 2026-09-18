<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:verify,reject,needs_resubmission'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
