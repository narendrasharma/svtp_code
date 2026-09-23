<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authoritative locale middleware (Phase 13A, shared platform).
 *
 * Resolution: explicit session → explicit cookie → platform default.
 * Never overrides an explicit choice with Accept-Language. Always
 * falls back safely for inactive/invalid selections.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Localization::resolveLocale($request);

        app()->setLocale($locale);
        app()->setFallbackLocale((string) config('app.fallback_locale', 'en'));

        return $next($request);
    }
}
