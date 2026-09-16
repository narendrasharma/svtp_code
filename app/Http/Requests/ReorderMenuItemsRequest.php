<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ReorderMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:1000'],
            'items.*' => ['required', 'array:id,parent_id,sort_order'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.parent_id' => ['present', 'nullable', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @param array<int, int> $existingIds */
    public function validateHierarchy(array $existingIds): void
    {
        $items = collect($this->validated('items'))->keyBy('id');
        $submittedIds = $items->keys()->map(fn ($id): int => (int) $id)->all();
        sort($existingIds);
        sort($submittedIds);

        if ($existingIds !== $submittedIds) {
            throw ValidationException::withMessages(['items' => 'The menu has changed or contains invalid items. Reload the builder and try again.']);
        }

        $positions = [];
        foreach ($items as $item) {
            $parentId = $item['parent_id'];
            if ($parentId !== null && ! $items->has($parentId)) {
                throw ValidationException::withMessages(['items' => 'Every parent must belong to this menu.']);
            }

            $position = ($parentId ?? 'root').':'.$item['sort_order'];
            if (isset($positions[$position])) {
                throw ValidationException::withMessages(['items' => 'Sibling positions must be unique.']);
            }
            $positions[$position] = true;

            $visited = [$item['id'] => true];
            while ($parentId !== null) {
                if (isset($visited[$parentId]) || ! $items->has($parentId)) {
                    throw ValidationException::withMessages(['items' => 'A menu item cannot be nested inside itself or its descendants.']);
                }
                $visited[$parentId] = true;
                $parentId = $items->get($parentId)['parent_id'];
            }
        }
    }
}
