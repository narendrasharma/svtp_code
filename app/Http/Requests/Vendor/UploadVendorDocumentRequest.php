<?php

namespace App\Http\Requests\Vendor;

use App\Enums\VendorDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadVendorDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $maxKb = config('vendor_kyc.max_file_size_kb', 5120);
        $mimes = config('vendor_kyc.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']);

        return [
            'document_type' => ['required', 'string', Rule::in(VendorDocumentType::values())],
            'document_file' => ['required', 'file', 'mimes:'.implode(',', $mimes), 'max:'.$maxKb],
            'document_number_masked' => ['nullable', 'string', 'max:50'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
