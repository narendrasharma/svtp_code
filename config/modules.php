<?php

/*
 * -------------------------------------------------------------------------
 * Platform modules (Phase 11.5A platform core foundation).
 * -------------------------------------------------------------------------
 * Tours is the current primary module. Taxi and Hotels are registered here
 * as future module metadata only — they have no routes, controllers or UI
 * beyond the Modules admin page, which reports them as "not installed yet".
 *
 * State resolution lives in App\Support\ModuleManager. Never scatter
 * setting('taxi_enabled') style checks through controllers — always ask
 * the manager: app(ModuleManager::class)->isEnabled('tours').
 */
return [
    'tours' => [
        'key' => 'tours',
        'name' => 'Tours',
        'description' => 'Tour packages, destinations, places, categories, add-ons and availability.',
        'available' => true,
        'enabled_by_default' => true,
        'order' => 10,
    ],

    'taxi' => [
        'key' => 'taxi',
        'name' => 'Taxi',
        'description' => 'Taxi rides and airport transfers: fleet, drivers and bookings.',
        'available' => true,
        'enabled_by_default' => true,
        'order' => 20,
    ],

    'hotels' => [
        'key' => 'hotels',
        'name' => 'Hotels',
        'description' => 'Hotel and property listings: types, amenities, galleries and publishing.',
        'available' => true,
        'enabled_by_default' => false,
        'order' => 30,
    ],
];
