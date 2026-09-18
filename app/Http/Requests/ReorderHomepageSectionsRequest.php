<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ReorderHomepageSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'sections' => ['required', 'array', 'min:1', 'max:100'],
            'sections.*' => ['required', 'array:id,sort_order'],
            'sections.*.id' => ['required', 'integer', 'distinct', 'exists:homepage_sections,id'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @param array<int, int> $existingIds */
    public function validateCompleteSet(array $existingIds): void
    {
        $submitted = collect($this->validated('sections'));
        $submittedIds = $submitted->pluck('id')->map(fn ($id): int => (int) $id)->all();
        sort($existingIds);
        sort($submittedIds);

        if ($existingIds !== $submittedIds) {
            throw ValidationException::withMessages(['sections' => 'The section list has changed. Reload the manager and try again.']);
        }

        if ($submitted->pluck('sort_order')->unique()->count() !== $submitted->count()) {
            throw ValidationException::withMessages(['sections' => 'Every section needs a unique position.']);
        }
    }
}
