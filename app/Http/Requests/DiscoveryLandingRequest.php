<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Landing contract lookup validation (Phase 13C).
 */
class DiscoveryLandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['city', 'destination', 'place'])],
            'id' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
