<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;

class BlockSensitiveActionsDuringImpersonation
{
    public function handle(Request $request, Closure $next)
    {
        if (ImpersonationService::active()) {
            abort(403, 'This action is not allowed while impersonating.');
        }

        return $next($request);
    }
}
