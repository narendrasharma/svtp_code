<?php

namespace App\Http\Middleware;

use App\Support\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block routes belonging to a disabled platform module.
 *
 * Usage: ->middleware('module:tours'). Disabled modules answer 404 so
 * their existence is not leaked; shared platform routes never use this.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $manager = app(ModuleManager::class);

        if (! $manager->isKnown($module) || $manager->isDisabled($module)) {
            abort(404, 'This section is not available.');
        }

        return $next($request);
    }
}
