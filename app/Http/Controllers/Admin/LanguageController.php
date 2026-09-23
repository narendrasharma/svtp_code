<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared platform language management (Phase 13A).
 *
 * Module-independent: available regardless of Hotels/Tours/Taxi state.
 * Permissions reuse settings.view (read) / settings.update (mutate).
 */
class LanguageController extends Controller
{
    public function index(): Response
    {
        $languages = Language::query()->ordered()->get();

        return Inertia::render('Admin/Languages/Index', [
            'languages' => $languages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Languages/Form', [
            'language' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateLanguage($request);

        if (($validated['is_default'] ?? false)) {
            $validated['is_active'] = true;
            Language::query()->where('is_default', true)->update(['is_default' => false]);
        }

        Language::create($validated);
        Localization::forgetCache();

        // First language ever created becomes default automatically.
        if (Language::query()->where('is_default', true)->count() === 0) {
            $created = Language::query()->orderBy('id')->first();
            $created?->forceFill(['is_default' => true, 'is_active' => true])->save();
            Localization::forgetCache();
        }

        return redirect()->route('admin.languages.index')->with('flash', 'Language saved.');
    }

    public function edit(Language $language): Response
    {
        return Inertia::render('Admin/Languages/Form', [
            'language' => $language,
        ]);
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $validated = $this->validateLanguage($request, $language);

        $wantsDefault = (bool) ($validated['is_default'] ?? false);
        $wantsInactive = ! (bool) ($validated['is_active'] ?? true);

        // Invariant: default must stay active.
        if ($wantsDefault) {
            $validated['is_active'] = true;
        }

        // Invariant: cannot deactivate the only default without replacement.
        if ($language->is_default && $wantsInactive && ! $wantsDefault) {
            $otherDefault = Language::query()
                ->whereKeyNot($language->id)
                ->where('is_default', true)
                ->exists();

            if (! $otherDefault) {
                return back()->withErrors([
                    'is_active' => 'The default language must stay active. Set another default first.',
                ]);
            }
        }

        // Invariant: unsetting default requires another default present,
        // otherwise keep it (never allow zero defaults).
        if ($language->is_default && ! $wantsDefault) {
            $otherDefault = Language::query()
                ->whereKeyNot($language->id)
                ->where('is_default', true)
                ->exists();

            if (! $otherDefault) {
                $validated['is_default'] = true;
                $validated['is_active'] = true;
            }
        }

        if (! empty($validated['is_default'])) {
            Language::query()->whereKeyNot($language->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $language->update($validated);
        Localization::forgetCache();

        return redirect()->route('admin.languages.index')->with('flash', 'Language updated.');
    }

    public function setDefault(Language $language): RedirectResponse
    {
        if (! $language->is_active) {
            return back()->withErrors(['is_default' => 'Only an active language can be the default.']);
        }

        Language::query()->where('is_default', true)->update(['is_default' => false]);
        $language->forceFill(['is_default' => true, 'is_active' => true])->save();
        Localization::forgetCache();

        return back()->with('flash', 'Default language updated.');
    }

    public function toggle(Language $language): RedirectResponse
    {
        if ($language->is_default && $language->is_active) {
            return back()->withErrors(['is_active' => 'The default language cannot be deactivated. Set another default first.']);
        }

        $language->forceFill(['is_active' => ! $language->is_active])->save();
        Localization::forgetCache();

        return back()->with('flash', 'Language status updated.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        if ($language->is_default) {
            return back()->withErrors(['language' => 'The default language cannot be deleted. Set another default first.']);
        }

        if (Language::query()->count() <= 1) {
            return back()->withErrors(['language' => 'At least one language must remain.']);
        }

        $language->delete();
        Localization::forgetCache();

        return back()->with('flash', 'Language removed. Stored translations were kept out of this registry by design (translation rows reference locales, not language ids).');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateLanguage(Request $request, ?Language $language = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:12',
                'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/i',
                Rule::unique('languages', 'code')->ignore($language?->id),
            ],
            'locale' => [
                'required',
                'string',
                'max:12',
                'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/i',
                Rule::unique('languages', 'locale')->ignore($language?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'native_name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'is_rtl' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'date_format' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
