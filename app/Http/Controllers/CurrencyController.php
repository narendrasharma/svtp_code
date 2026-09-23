<?php

namespace App\Http\Controllers;

use App\Support\CurrencyRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Persistent visitor display-currency selection (Phase 13B).
 *
 * DISPLAY ONLY: switching currency never rewrites authoritative
 * prices. Guest-safe via session + first-party cookie (13A pattern).
 */
class CurrencyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'max:3'],
        ]);

        $code = CurrencyRegistry::normalizeCode($validated['currency']);

        if ($code === '' || ! CurrencyRegistry::isActiveCode($code)) {
            $code = CurrencyRegistry::defaultDisplayCode();
        }

        $request->session()->put(CurrencyRegistry::SESSION_KEY, $code);

        Cookie::queue(
            Cookie::make(
                CurrencyRegistry::COOKIE_KEY,
                $code,
                CurrencyRegistry::COOKIE_MINUTES,
                config('session.path', '/'),
                config('session.domain'),
                (bool) config('session.secure', false),
                false,
                false,
                config('session.same_site', 'lax')
            )
        );

        return back()->with('flash', 'Display currency updated.');
    }
}
