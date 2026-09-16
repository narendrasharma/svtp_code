<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMenuPagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'page_ids' => ['required', 'array', 'min:1', 'max:100'],
            'page_ids.*' => ['required', 'integer', 'distinct', Rule::exists('pages', 'id')->where('is_active', true)],
        ];
    }
}
