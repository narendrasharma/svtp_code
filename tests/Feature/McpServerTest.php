<?php

namespace Tests\Feature;

use App\AI\Contracts\KnowledgeRetrieverInterface;
use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\AIException;
use App\Models\McpAccessToken;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_disabled_mcp_rejects_requests(): void
    {
        $this->mcp('tools/list')->assertStatus(503)->assertJsonPath('error', 'MCP is disabled.');
        [$token] = $this->issueToken();
        Setting::setValue('mcp.enabled', '0');
        $this->mcp('tools/list', [], $token)->assertStatus(503)->assertJsonPath('error', 'MCP is disabled.');
    }

    public function test_invalid_token_is_rejected(): void
    {
        Setting::setValue('mcp.enabled', '1');
        $this->mcp('tools/list', [], 'mcp_1.'.str_repeat('a', 64))->assertUnauthorized();
    }

    public function test_admin_lists_only_registered_read_tools_from_existing_schemas(): void
    {
        [$token] = $this->issueToken();
        $response = $this->mcp('tools/list', [], $token)->assertOk();
        $names = array_column($response->json('result.tools'), 'name');

        $this->assertContains('search_tours', $names);
        $this->assertContains('search_knowledge', $names);
        $this->assertSame(['query', 'city', 'destination', 'status', 'featured', 'limit'],
            array_keys(collect($response->json('result.tools'))->firstWhere('name', 'search_tours')['inputSchema']['properties']));
        $this->assertNotContains('set_tour_featured', $names);

        $handshake = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-06-18',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'triparo-test', 'version' => '1.0.0']],
        ])->assertOk();
        $this->assertNotEmpty($handshake->headers->get('Mcp-Session-Id'));
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-06-18',
            'Mcp-Session-Id' => $handshake->headers->get('Mcp-Session-Id'),
        ])->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => new \stdClass])
            ->assertOk()->assertJsonPath('result.tools.0.name', 'search_tours');
    }

    public function test_staff_permissions_remove_unavailable_tools(): void
    {
        [$token, $user] = $this->issueToken();
        $role = Role::create(['name' => 'mcp-tour-reader', 'guard_name' => 'web']);
        $role->givePermissionTo(['ai.assistant.use', 'tours.view']);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = array_column($this->mcp('tools/list', [], $token)->assertOk()->json('result.tools'), 'name');
        $this->assertContains('search_tours', $names);
        $this->assertNotContains('search_tour_bookings', $names);
        $this->assertNotContains('marketplace_overview', $names);

        $role->revokePermissionTo('ai.assistant.use');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->mcp('tools/list', [], $token)->assertForbidden();
    }

    public function test_search_tours_uses_existing_safe_read_tool_and_writes_audit(): void
    {
        [$token, $user] = $this->issueToken();
        TourPackage::factory()->create(['title' => 'MCP Agra Tour', 'price' => 9999]);

        $response = $this->mcp('tools/call', ['name' => 'search_tours', 'arguments' => ['query' => 'Agra']], $token)
            ->assertOk()->assertJsonPath('result.isError', false);

        $this->assertStringContainsString('MCP Agra Tour', json_encode($response->json('result.structuredContent')));
        $this->assertStringNotContainsString('9999', json_encode($response->json('result.structuredContent')));
        $this->assertDatabaseHas('activity_logs', ['event' => 'mcp.tool.call', 'actor_user_id' => $user->id]);
    }

    public function test_search_knowledge_reuses_existing_unavailable_result(): void
    {
        $this->app->instance(KnowledgeRetrieverInterface::class, new class implements KnowledgeRetrieverInterface
        {
            public function search(string $query, AIExecutionContext $context, int $limit = 5): array
            {
                throw AIException::unconfigured();
            }
        });
        [$token] = $this->issueToken();

        $this->mcp('tools/call', ['name' => 'search_knowledge', 'arguments' => ['query' => 'policy']], $token)
            ->assertOk()->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.reason', 'embedding_unconfigured');
    }

    public function test_write_tools_cannot_be_called_even_when_internal_actions_are_enabled(): void
    {
        Setting::setValue('ai.agent_actions_enabled', '1');
        [$token] = $this->issueToken();
        $this->mcp('tools/call', ['name' => 'set_tour_featured', 'arguments' => ['id' => 1, 'featured' => true]], $token)
            ->assertStatus(400)->assertJsonPath('error.code', -32602);
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_revoked_token_stops_working_and_plaintext_is_never_stored(): void
    {
        Setting::setValue('mcp.enabled', '1');
        $user = User::factory()->create(['role' => 'admin']);
        $bearer = $this->actingAs($user)->postJson(route('admin.mcp.tokens.store'), ['label' => 'test host'])
            ->assertCreated()->json('token');
        $token = McpAccessToken::query()->sole();
        $this->assertIsString($bearer);
        $this->assertNotSame($bearer, $token->token_hash);
        $this->assertSame(64, strlen($token->token_hash));
        $this->mcp('tools/list', [], $bearer)->assertOk();

        $this->actingAs($user)->delete(route('admin.mcp.tokens.revoke', $token))->assertRedirect();
        $this->mcp('tools/list', [], $bearer)->assertUnauthorized();
    }

    /** @return array{string, User, McpAccessToken} */
    private function issueToken(): array
    {
        Setting::setValue('mcp.enabled', '1');
        $user = User::factory()->create(['role' => 'admin']);
        $secret = bin2hex(random_bytes(32));
        $token = McpAccessToken::create([
            'user_id' => $user->id,
            'label' => 'test host',
            'token_hash' => hash('sha256', $secret),
            'expires_at' => now()->addDay(),
        ]);

        return ["mcp_{$token->id}.{$secret}", $user, $token];
    }

    /** @param array<string, mixed> $params */
    private function mcp(string $method, array $params = [], ?string $token = null): TestResponse
    {
        $params['_meta'] = [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => [],
        ];
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => $method,
        ];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        if (is_string($params['name'] ?? null)) {
            $headers['Mcp-Name'] = $params['name'];
        }

        return $this->withHeaders($headers)->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params,
        ]);
    }
}
