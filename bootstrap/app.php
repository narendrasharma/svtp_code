<?php

use App\Http\Middleware\BlockSensitiveActionsDuringImpersonation;
use App\Http\Middleware\EnsureDriverIdentity;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureStaffPermission;
use App\Http\Middleware\EnsureTrackingPrivacyHeaders;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsVendor;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'vendor' => EnsureUserIsVendor::class,
            // Phase 12A.4 driver portal (linked Driver record required).
            'driver' => EnsureDriverIdentity::class,
            // Phase 12A.9 public tracking privacy headers.
            'tracking.privacy' => EnsureTrackingPrivacyHeaders::class,
            'block.impersonated.sensitive' => BlockSensitiveActionsDuringImpersonation::class,
            // Phase 11.5A platform core.
            'module' => EnsureModuleEnabled::class,
            'staff.permissions' => EnsureStaffPermission::class,
        ]);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
