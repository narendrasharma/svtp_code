<?php

namespace App\Http\Controllers;

use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Persistent customer language selection (Phase 13A).
 *
 * Guest-safe: session + small first-party cookie, no account schema.
 * Inactive/invalid locales fall back to the platform default.
 */
class LocaleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:12'],
        ]);

        $locale = Localization::normalizeCode($validated['locale']);

        if ($locale === '' || ! Localization::isActiveLocale($locale)) {
            $locale = Localization::defaultLocale();
        }

        $request->session()->put(Localization::SESSION_KEY, $locale);

        Cookie::queue(
            Cookie::make(
                Localization::COOKIE_KEY,
                $locale,
                Localization::COOKIE_MINUTES,
                config('session.path', '/'),
                config('session.domain'),
                (bool) config('session.secure', false),
                false, // readable client-side for dir/lang hydration safety
                false,
                config('session.same_site', 'lax')
            )
        );

        return back()->with('flash', __('common.language_updated'));
    }
}
