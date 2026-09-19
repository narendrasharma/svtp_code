<?php

namespace App\Http\Middleware;

use App\Models\Driver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Driver Portal access (Phase 12A.4).
 *
 * Drivers reuse the standard User authentication — there is no separate
 * driver guard. A portal user must have a linked Driver record through
 * drivers.user_id and that record must be commercially active. Every
 * trip endpoint additionally validates an open assignment (see
 * DriverPortalController), so identity here is only the front gate.
 */
class EnsureDriverIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $driver = $user ? Driver::where('user_id', $user->id)->first() : null;

        if (! $driver) {
            abort(403, 'Driver access only. This login is not linked to a driver.');
        }

        if (! $driver->is_active || $driver->employment_status !== 'active') {
            abort(403, 'This driver account is not active.');
        }

        return $next($request);
    }
}
