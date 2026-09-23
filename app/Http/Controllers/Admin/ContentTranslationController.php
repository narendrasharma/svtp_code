<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\HomepageSection;
use App\Models\Language;
use App\Models\Page;
use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Representative translation write endpoint (Phase 13A).
 *
 * Only explicitly whitelisted model fields may be saved — the request
 * can never name arbitrary columns (ownership/price/status safe).
 */
class ContentTranslationController extends Controller
{
    /**
     * @return array<string, class-string>
     */
    public static function allowedModels(): array
    {
        return [
            'destination' => Destination::class,
            'homepage_section' => HomepageSection::class,
            'page' => Page::class,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string', 'in:destination,homepage_section,page'],
            'id' => ['required', 'integer', 'min:1'],
            'locale' => ['required', 'string', 'max:12'],
            'translations' => ['required', 'array'],
        ]);

        $modelClass = static::allowedModels()[$validated['model']];
        $locale = Localization::normalizeCode($validated['locale']);

        $knownLocales = array_map(
            fn ($language): string => strtolower((string) $language['locale']),
            Localization::activeLanguages()
        );

        // Allow saving for any configured locale (active or inactive demo
        // option): inactive translations stay stored but not selectable.
        if ($locale === '' || (! in_array($locale, $knownLocales, true) && $locale !== strtolower(Localization::defaultLocale()))) {
            // Fall back to validating against the languages table registry.
            $exists = Language::query()->where('locale', $locale)->orWhere('code', $locale)->exists();

            if (! $exists) {
                return back()->withErrors(['locale' => 'Unknown locale.']);
            }
        }

        /** @var Destination|HomepageSection|Page $entity */
        $entity = $modelClass::query()->findOrFail($validated['id']);

        $allowed = $modelClass::translatableFields();

        foreach ((array) $validated['translations'] as $field => $value) {
            if (! in_array($field, $allowed, true)) {
                return back()->withErrors(['translations' => "Field [{$field}] cannot be translated."]);
            }

            if (! is_string($value) && $value !== null) {
                return back()->withErrors(['translations' => "Field [{$field}] must be text."]);
            }

            if (is_string($value) && mb_strlen($value) > 65535) {
                return back()->withErrors(['translations' => "Field [{$field}] is too long."]);
            }

            // Plain-text SEO/name fields must never become raw HTML
            // injection vectors here; rich content (Page content) keeps
            // the trusted-editor strategy of the base model.
            $entity->setTranslation($locale, $field, $value);
        }

        return back()->with('flash', 'Translation saved.');
    }
}
