<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared unified search & discovery defaults (Phase 13C)
    |--------------------------------------------------------------------------
    |
    | Geography/discovery itself is SHARED and module-independent. Hotel,
    | Tour and Taxi result slices respect ModuleManager gating at the
    | service layer — never at the City/Destination/Place layer.
    |
    | Default driver is `database`. The SearchProvider contract seam
    | allows future Scout/Meilisearch/Algolia adapters without touching
    | callers; nothing here requires external infrastructure.
    |
    */

    'driver' => env('SEARCH_DRIVER', 'database'),

    'autocomplete_limit' => (int) env('SEARCH_AUTOCOMPLETE_LIMIT', 10),

    'per_type_limit' => (int) env('SEARCH_PER_TYPE_LIMIT', 4),

    'minimum_query_length' => (int) env('SEARCH_MIN_QUERY_LENGTH', 2),

    'max_query_length' => 80,

    'hotel_per_page' => 12,

    'tour_per_page' => 9,

    'max_per_page' => 24,

    // Whitelisted public sort keys only. Request values map to safe
    // strategies in the domain services — never to raw orderBy input.
    'hotel_sorts' => ['recommended', 'price_asc', 'price_desc', 'rating_desc'],

    'tour_sorts' => ['recommended', 'price_asc', 'price_desc', 'duration_asc'],
];
