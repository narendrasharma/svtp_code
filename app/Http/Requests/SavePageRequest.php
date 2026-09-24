<?php

namespace App\Http\Requests;

use App\Models\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SavePageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $slug = (string) $this->input('slug');
        $page = $this->route('page');

        $this->merge([
            'slug' => $page instanceof Page && $slug === $page->slug
                ? $page->slug
                : Str::slug($slug),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        $pageId = $page instanceof Page ? $page->getKey() : $page;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:191', Rule::unique('pages', 'slug')->ignore($pageId)],
            'template' => ['required', 'string', Rule::in(Page::availableTemplates())],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (in_array(strtolower((string) $this->input('slug')), $this->reservedSlugs(), true)) {
                $validator->errors()->add('slug', 'This permalink is reserved by the application. Choose another slug.');
            }
        });
    }

    /** @return array<int, string> */
    protected function reservedSlugs(): array
    {
        $reserved = ['admin', 'api', 'build', 'storage'];

        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0] ?? '';

            if ($first !== '' && ! str_starts_with($first, '{')) {
                $reserved[] = strtolower($first);
            }
        }

        return array_values(array_unique($reserved));
    }
}
