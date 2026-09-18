<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reusable lead-source catalogue. Deleting a source with leads is
 * refused — deactivate it instead so attribution history survives.
 */
class LeadSourceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/LeadSources/Index', [
            'sources' => LeadSource::withCount('leads')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        LeadSource::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('flash', 'Lead source added.');
    }

    public function update(Request $request, LeadSource $leadSource): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $leadSource->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? $leadSource->is_active,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('flash', 'Lead source updated.');
    }

    public function destroy(LeadSource $leadSource): RedirectResponse
    {
        if ($leadSource->leads()->exists()) {
            return back()->withErrors(['source' => 'This source has leads attached. Deactivate it instead of deleting.']);
        }

        $leadSource->delete();

        return back()->with('flash', 'Lead source removed.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'source';
        $slug = $base;
        $i = 2;

        while (LeadSource::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
