<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Unified location autocomplete validation (Phase 13C).
 *
 * Language-neutral identifiers; human labels translate separately.
 */
class DiscoveryLocationRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'mode' => ['sometimes', 'in:suggest,popular'],
        ];
    }

    public function mode(): string
    {
        $mode = $this->input('mode', 'suggest');

        return $mode === 'popular' ? 'popular' : 'suggest';
    }
}
