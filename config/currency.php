<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared multi-currency defaults (Phase 13B)
    |--------------------------------------------------------------------------
    |
    | The `currencies` table owns the default DISPLAY currency. These
    | values are FX plumbing only. Provider secrets live in .env and
    | are NEVER shared through Inertia props.
    |
    */

    'provider' => env('CURRENCY_PROVIDER', 'manual'),

    'fx_base' => env('CURRENCY_FX_BASE', 'USD'),

    'max_rate_age_hours' => (int) env('CURRENCY_MAX_RATE_AGE_HOURS', 24),
];
