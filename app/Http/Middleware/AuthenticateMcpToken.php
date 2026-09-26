<?php

namespace App\Http\Middleware;

use App\Models\McpAccessToken;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(Setting::getValue('mcp.enabled', config('mcp.enabled', false)), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['error' => 'MCP is disabled.'], 503);
        }

        $bearer = $request->bearerToken();
        if (! is_string($bearer) || ! preg_match('/^mcp_(\d+)\.([a-f0-9]{64})$/D', $bearer, $matches)) {
            return $this->unauthenticated();
        }

        $token = McpAccessToken::query()->with('user')->find((int) $matches[1]);
        if (! $token || ! hash_equals($token->token_hash, hash('sha256', $matches[2]))
            || $token->revoked_at !== null || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return $this->unauthenticated();
        }
        if (! $token->user?->isAdmin() || ! $token->user->hasStaffPermission('ai.assistant.use')) {
            return response()->json(['error' => 'MCP access is forbidden for this account.'], 403);
        }

        $rateKey = 'mcp:token:'.$token->id;
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            return response()->json(['error' => 'MCP rate limit reached.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($rateKey));
        }
        RateLimiter::hit($rateKey, 60);

        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('mcp_token', $token);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json(['error' => 'Invalid MCP access token.'], 401)
            ->header('WWW-Authenticate', 'Bearer');
    }
}
