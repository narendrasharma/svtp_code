<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpAccessToken;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class McpAccessController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Mcp/Index', [
            'enabled' => filter_var(Setting::getValue('mcp.enabled', config('mcp.enabled', false)), FILTER_VALIDATE_BOOLEAN),
            'tokens' => McpAccessToken::query()->where('user_id', $request->user()->id)
                ->latest()->get(['id', 'label', 'created_at', 'last_used_at', 'expires_at', 'revoked_at']),
        ])->toResponse($request)->header('Cache-Control', 'no-store');
    }

    public function toggle(Request $request, ActivityLogger $audit): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        Setting::setValue('mcp.enabled', $data['enabled'] ? '1' : '0');
        $audit->log('mcp.enabled.updated', 'mcp', $data['enabled'] ? 'MCP enabled' : 'MCP disabled', actor: $request->user());

        return back()->with('message', $data['enabled'] ? 'MCP enabled.' : 'MCP disabled.');
    }

    public function store(Request $request, ActivityLogger $audit): JsonResponse
    {
        abort_unless($request->user()->hasStaffPermission('ai.assistant.use'), 403);
        $data = $request->validate(['label' => ['required', 'string', 'max:100']]);
        $secret = bin2hex(random_bytes(32));
        $token = DB::transaction(function () use ($request, $data, $secret, $audit): McpAccessToken {
            $token = McpAccessToken::create([
                'user_id' => $request->user()->id,
                'label' => $data['label'],
                'token_hash' => hash('sha256', $secret),
                'expires_at' => now()->addDays(90),
            ]);
            $audit->log('mcp.token.created', 'mcp', 'MCP access token created', $token, null, ['label' => $token->label], $request->user());

            return $token;
        });

        return response()->json(['token' => "mcp_{$token->id}.{$secret}"], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function revoke(Request $request, McpAccessToken $token, ActivityLogger $audit): RedirectResponse
    {
        abort_unless($token->user_id === $request->user()->id, 403);
        if ($token->revoked_at === null) {
            $token->forceFill(['revoked_at' => now()])->save();
            $audit->log('mcp.token.revoked', 'mcp', 'MCP access token revoked', $token, null, ['label' => $token->label], $request->user());
        }

        return back()->with('message', 'Token revoked.');
    }
}
