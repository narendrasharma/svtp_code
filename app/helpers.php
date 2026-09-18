<?php

/*
 * Global platform helpers (Phase 11.5A).
 */

use App\Support\ModuleManager;

if (! function_exists('modules')) {
    /**
     * Access the central platform Module Manager.
     */
    function modules(): ModuleManager
    {
        return app(ModuleManager::class);
    }
}
