<?php

namespace App\Http\Requests;

use App\Models\Menu;
use App\Rules\MenuLinkUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['page', 'custom'])],
            'page_id' => ['required_if:type,page', 'nullable', 'exists:pages,id'],
            'url' => ['required_if:type,custom', 'nullable', 'string', 'max:255', new MenuLinkUrl],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $menu = $this->route('menu');
            if (! $menu instanceof Menu) {
                return;
            }
            $parentId = $this->input('parent_id');
            if ($parentId === null) {
                return;
            }
            $parents = $menu->items()->pluck('parent_id', 'id');
            $item = $this->route('menuItem');
            $visited = $item ? [$item->id => true] : [];
            while ($parentId !== null) {
                if (! $parents->has($parentId)) {
                    $validator->errors()->add('parent_id', 'Parent item must belong to the same menu.');

                    return;
                }
                if (isset($visited[$parentId])) {
                    $validator->errors()->add('parent_id', 'A menu item cannot be nested inside itself or its descendants.');

                    return;
                }
                $visited[$parentId] = true;
                $parentId = $parents->get($parentId);
            }
        });
    }
}
