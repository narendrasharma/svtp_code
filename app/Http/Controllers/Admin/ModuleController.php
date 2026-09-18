<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform module management (Phase 11.5A). Tours can be switched on/off;
 * taxi/hotels are registered future modules and report as not installed.
 */
class ModuleController extends Controller
{
    public function index(ModuleManager $modules): Response
    {
        return Inertia::render('Admin/Modules/Index', [
            'modules' => $modules->all(),
        ]);
    }

    public function update(Request $request, string $module, ModuleManager $modules): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        try {
            $modules->setEnabled($module, (bool) $validated['enabled']);
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $state = $validated['enabled'] ? 'enabled' : 'disabled';

        app(ActivityLogger::class)->log('module.toggled', 'system', "Module [{$module}] {$state}.");

        return back()->with('flash', "Module [{$module}] {$state}. Applies immediately to navigation, search and module routes.");
    }
}
