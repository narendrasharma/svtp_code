<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupportCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Support/Categories', [
            'categories' => SupportCategory::withCount('tickets')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        SupportCategory::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('flash', 'Category added.');
    }

    public function update(Request $request, SupportCategory $supportCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $supportCategory->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? $supportCategory->is_active,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('flash', 'Category updated.');
    }

    public function destroy(SupportCategory $supportCategory): RedirectResponse
    {
        if ($supportCategory->tickets()->exists()) {
            return back()->withErrors(['category' => 'This category has tickets attached. Deactivate it instead of deleting.']);
        }

        $supportCategory->delete();

        return back()->with('flash', 'Category removed.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (SupportCategory::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
