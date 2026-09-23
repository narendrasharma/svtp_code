<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared localization defaults (Phase 13A)
    |--------------------------------------------------------------------------
    |
    | The `languages` table is the single authoritative source for the
    | default/active state. These config values are only install-time
    | fallbacks (fresh install before migration, installer, tests).
    |
    */

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'cookie' => env('LOCALIZATION_COOKIE', 'locale'),

    'cookie_minutes' => 525600, // 12 months
];
