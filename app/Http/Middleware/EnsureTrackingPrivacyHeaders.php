<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privacy headers for the public customer tracking pages (12A.9).
 *
 * Live trip pages must not be indexed or publicly cached. Applied only
 * to the token-authenticated tracking routes.
 */
class EnsureTrackingPrivacyHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
