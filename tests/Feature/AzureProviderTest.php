<?php

namespace Tests\Feature;

use App\AI\DTOs\AIMessage;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIToolRequest;
use App\AI\Support\AIException;
use App\AI\Support\AIManager;
use App\AI\Support\AISettings;
use App\AI\Support\KnowledgeIndexingService;
use App\Models\ActivityLog;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class AzureProviderTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://triparo.services.ai.azure.com/api/projects/triparo/openai/v1/responses';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Http::preventStrayRequests();
        Setting::setValue('ai.enabled', '1');
        Setting::setValue('ai.provider', 'azure');
        Setting::setValue('ai.model', 'triparo-gpt');
        Setting::setValue('ai.azure.endpoint', 'https://triparo.services.ai.azure.com/api/projects/triparo');
        Setting::setValue('ai.azure.tool_calling', '1');
        config()->set('services.ai.providers.azure.key', 'fake-azure-key');
    }

    public function test_content_response_is_normalized_through_responses_v1(): void
    {
        Http::fake([self::URL => Http::response([
            'status' => 'completed', 'model' => 'triparo-gpt',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'A polished tour draft.']]]],
            'usage' => ['input_tokens' => 12, 'output_tokens' => 6, 'total_tokens' => 18],
        ], 200, ['apim-request-id' => 'azure-123'])]);

        $response = app(AIManager::class)->generate(new AIRequest('Rewrite this tour.', 'Keep it short.', maxTokens: 200), 'content_copilot.tour.rewrite');

        $this->assertSame('A polished tour draft.', $response->text);
        $this->assertSame('azure', $response->provider);
        $this->assertSame(['input_tokens' => 12, 'output_tokens' => 6, 'total_tokens' => 18], $response->usage);
        $this->assertSame('azure-123', $response->requestId);
        Http::assertSent(fn ($request): bool => $request->url() === self::URL
            && $request->hasHeader('api-key', 'fake-azure-key')
            && $request['model'] === 'triparo-gpt' && $request['instructions'] === 'Keep it short.'
            && $request['max_output_tokens'] === 200 && $request['store'] === false);
    }

    public function test_function_declaration_call_and_read_result_roundtrip(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(['status' => 'completed', 'output' => [[
                'type' => 'function_call', 'id' => 'fc_remote', 'call_id' => 'call-1',
                'name' => 'search_tours', 'arguments' => '{"query":"Agra"}',
            ]]])
            ->push(['status' => 'completed', 'output' => [[
                'type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'One tour found.']],
            ]]])]);
        $tool = ['name' => 'search_tours', 'description' => 'Find tours', 'input_schema' => [
            'type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'additionalProperties' => false,
        ]];
        $messages = [new AIMessage('system', 'Use tools.'), new AIMessage('user', 'Find Agra tours.')];

        $first = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, [$tool], true));
        $this->assertSame('search_tours', $first->toolCalls[0]->name);
        $this->assertSame('call-1', $first->toolCalls[0]->id);
        $this->assertSame(['query' => 'Agra'], $first->toolCalls[0]->arguments);
        $messages[] = new AIMessage('assistant', $first->text, $first->toolCalls, providerState: $first->providerState);
        $messages[] = new AIMessage('tool', '{"items":[{"title":"Agra Tour"}]}', toolCallId: 'call-1');
        $second = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, [$tool]));

        $this->assertSame('One tour found.', $second->text);
        Http::assertSent(fn ($request): bool => $request['tool_choice'] === 'required'
            && $request['input'][0] === ['type' => 'message', 'role' => 'system', 'content' => 'Use tools.']
            && $request['input'][1] === ['type' => 'message', 'role' => 'user', 'content' => 'Find Agra tours.']
            && $request['tools'][0] === [
                'type' => 'function', 'name' => 'search_tours', 'description' => 'Find tours',
                'parameters' => $tool['input_schema'], 'strict' => false,
            ]);
        Http::assertSent(fn ($request): bool => ($request['input'][2]['type'] ?? null) === 'function_call'
            && ($request['input'][3]['type'] ?? null) === 'function_call_output'
            && $request['input'][3]['call_id'] === 'call-1'
            && $request['input'][3]['output'] === '{"items":[{"title":"Agra Tour"}]}');
        Http::assertSentCount(2);
    }

    public function test_agent_executes_existing_read_tool_and_conversation_survives_provider_switch(): void
    {
        TourPackage::factory()->approved()->create(['title' => 'Agra Heritage Walk']);
        Http::fake([self::URL => Http::sequence()
            ->push(['status' => 'completed', 'output' => [[
                'type' => 'function_call', 'call_id' => 'read-1', 'name' => 'search_tours',
                'arguments' => '{"query":"Agra Heritage"}',
            ]]])
            ->push(['status' => 'completed', 'output' => [[
                'type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Agra Heritage Walk is available.']],
            ]]])]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Find Agra Heritage tours'])
            ->assertOk()->assertJsonPath('answer', 'Agra Heritage Walk is available.')
            ->assertJsonPath('tools_used.0', 'search_tours');
        $this->assertSame('azure', AiConversation::firstOrFail()->provider);
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_tours', 'succeeded' => true]);
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Find tours'])
            ->assertForbidden();
        $this->actingAs(AiConversation::firstOrFail()->user);
        Setting::setValue('ai.provider', 'gemini');
        $this->getJson(route('admin.ai-assistant.conversations.show', AiConversation::firstOrFail()->id))
            ->assertOk()->assertJsonPath('messages.1.content', 'Agra Heritage Walk is available.');
    }

    public function test_azure_write_request_creates_a_pending_proposal_without_mutation(): void
    {
        Setting::setValue('ai.agent_actions.enabled', '1');
        $tour = TourPackage::factory()->approved()->create(['is_featured' => false]);
        Http::fake([self::URL => Http::response(['status' => 'completed', 'output' => [[
            'type' => 'function_call', 'call_id' => 'write-1', 'name' => 'set_tour_featured',
            'arguments' => json_encode(['tour_id' => $tour->id, 'featured' => true]),
        ]]])]);

        $user = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($user)
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Feature this tour'])
            ->assertOk()->assertJsonPath('proposal.status', 'pending')
            ->assertJsonPath('proposal.tool_name', 'set_tour_featured');

        $proposal = AiActionProposal::firstOrFail();
        $this->assertSame($user->id, $proposal->user_id);
        $this->assertSame('tour', $proposal->target_type);
        $this->assertSame($tour->id, $proposal->target_id);
        $this->assertSame(['tour_id' => $tour->id, 'featured' => true], $proposal->validated_arguments);
        $this->assertSame(['is_featured' => false], $proposal->before_snapshot);
        $this->assertSame(['is_featured' => true], $proposal->proposed_changes);
        $this->assertTrue($proposal->expires_at->isFuture());
        $audit = ActivityLog::query()->where('event', 'ai.action.proposed')->sole();
        $this->assertNull($audit->subject_id);
        $this->assertSame($proposal->id, $audit->new_values['proposal_id']);
        $this->assertFalse($tour->fresh()->is_featured);
        $this->assertDatabaseCount('ai_action_proposals', 1);
        $this->assertDatabaseCount('ai_messages', 2);
        $this->getJson(route('admin.ai-assistant.conversations.show', $response->json('conversation.id')))
            ->assertOk()->assertJsonPath('proposals.0.id', $proposal->id)
            ->assertJsonPath('messages.1.content', $response->json('answer'));

        $this->postJson(route('admin.ai-assistant.message'), [
            'question' => 'Feature this tour', 'conversation_id' => $response->json('conversation.id'),
        ])->assertOk()->assertJsonPath('proposal.id', $proposal->id);

        $this->assertDatabaseCount('ai_action_proposals', 1);
        $this->assertDatabaseCount('ai_messages', 4);
        $this->assertFalse($tour->fresh()->is_featured);
        $this->postJson(route('admin.ai-assistant.actions.confirm', $proposal->id))
            ->assertOk()->assertJsonPath('proposal.status', 'executed');
        $this->assertTrue($tour->fresh()->is_featured);
        $this->assertSame(1, ActivityLog::query()->where('event', 'ai.action.executed')->count());
        Http::assertSentCount(2);
    }

    public function test_azure_can_propose_explicit_feature_request_after_reading_the_target(): void
    {
        Setting::setValue('ai.agent_actions.enabled', '1');
        $tour = TourPackage::factory()->approved()->create([
            'title' => 'Mathura Vrindavan Tour from Agra (3 Nights / 4 Days)', 'is_featured' => false,
        ]);
        Http::fake([self::URL => Http::sequence()
            ->push(['status' => 'completed', 'output' => [[
                'type' => 'function_call', 'call_id' => 'read-1', 'name' => 'search_tours',
                'arguments' => json_encode(['query' => $tour->title]),
            ]]])
            ->push(['status' => 'completed', 'output' => [[
                'type' => 'function_call', 'call_id' => 'write-1', 'name' => 'set_tour_featured',
                'arguments' => json_encode(['tour_id' => $tour->id, 'featured' => true]),
            ]]])]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Make "'.$tour->title.'" featured.'])
            ->assertOk()->assertJsonPath('proposal.status', 'pending')
            ->assertJsonPath('proposal.target_id', $tour->id);

        $this->assertDatabaseCount('ai_action_proposals', 1);
        $this->assertFalse($tour->fresh()->is_featured);
        Http::assertSent(fn ($request): bool => $request['tool_choice'] === 'required'
            && str_contains($request['input'][0]['content'], 'READ results only to identify the exact target')
            && collect($request['tools'])->contains(fn (array $tool): bool => $tool['name'] === 'set_tour_featured'
                && str_contains($tool['description'], 'does not change or publish the Tour')));
        Http::assertSent(fn ($request): bool => collect($request['input'])->contains(fn (array $item): bool => ($item['type'] ?? null) === 'function_call_output')
            && collect($request['tools'])->contains(fn (array $tool): bool => $tool['name'] === 'set_tour_featured'));
        Http::assertSentCount(2);
    }

    public function test_azure_agent_uses_gemini_embedded_knowledge_independently(): void
    {
        Setting::setValue('ai.knowledge.enabled', '1');
        Setting::setValue('ai.embedding.provider', 'gemini');
        Setting::setValue('ai.embedding.model', 'gemini-embedding-2');
        config()->set('services.ai.providers.gemini.key', 'fake-gemini-key');
        $vector = array_fill(0, 768, 0.0);
        $vector[0] = 1.0;
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent' => Http::response(['embedding' => ['values' => $vector]]),
            self::URL => Http::sequence()
                ->push(['status' => 'completed', 'output' => [[
                    'type' => 'function_call', 'call_id' => 'knowledge-1', 'name' => 'search_knowledge',
                    'arguments' => '{"query":"hotel check-in policy"}',
                ]]])
                ->push(['status' => 'completed', 'output_text' => 'Check-in is at 2 PM.', 'output' => [[
                    'type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Check-in is at 2 PM.']],
                ]]]),
        ]);
        $document = AiKnowledgeDocument::factory()->create(['title' => 'Hotel Check-in Policy', 'content' => 'Check-in is at 2 PM.']);
        app(KnowledgeIndexingService::class)->index($document);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'What is the hotel check-in policy?'])
            ->assertOk()->assertJsonPath('answer', 'Check-in is at 2 PM.')
            ->assertJsonPath('sources.0.title', 'Hotel Check-in Policy')
            ->assertJsonPath('tools_used.0', 'search_knowledge');
        $this->assertDatabaseHas('ai_knowledge_chunks', ['embedding_provider' => 'gemini', 'embedding_model' => 'gemini-embedding-2']);
    }

    public function test_capability_requires_explicit_deployment_support(): void
    {
        config()->set('services.ai.providers.azure.key', null);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.ai.update'), [
                'ai_enabled' => true,
                'ai_provider' => 'azure',
                'ai_model' => 'triparo-gpt',
                'azure_endpoint' => 'https://triparo.services.ai.azure.com/api/projects/triparo',
                'azure_tool_calling' => true,
                'api_key' => 'stored-azure-key',
                'knowledge_enabled' => false,
                'agent_actions_enabled' => false,
                'embedding_provider' => 'gemini',
                'embedding_model' => 'gemini-embedding-2',
            ])->assertRedirect()->assertSessionHasNoErrors();
        $manager = app(AIManager::class);
        $this->assertTrue($manager->capabilities()->supports('tool_calling'));
        $this->assertSame('stored-azure-key', app(AISettings::class)->credential('azure'));
        $this->assertNotSame('stored-azure-key', Setting::getValue('ai.azure.key'));

        Setting::setValue('ai.azure.tool_calling', '0');
        $this->assertFalse($manager->capabilities()->supports('tool_calling'));
        $this->assertTrue($manager->capabilities()->supports('text_generation'));

        try {
            $manager->chatWithTools(new AIToolRequest([new AIMessage('user', 'Find tours')], []));
            $this->fail('Unsupported deployment accepted an assistant request.');
        } catch (AIException $exception) {
            $this->assertSame('unsupported_tools', $exception->reason);
        }

        Http::assertNothingSent();
    }

    public function test_bad_credentials_are_not_retried_and_transient_failure_is_bounded(): void
    {
        Log::spy();
        Http::fake([self::URL => Http::sequence()
            ->push(['error' => ['code' => 'invalid_api_key', 'type' => 'authentication_error', 'message' => 'Bad credential fake-azure-key']], 401)
            ->push(['error' => ['code' => 'server_error', 'message' => 'secret response']], 503)
            ->push(['status' => 'completed', 'output_text' => 'Recovered.'])
            ->push(['error' => ['code' => 'OperationNotSupported', 'message' => 'Tool calling is not supported by this deployment.']], 400)]);

        try {
            app(AIManager::class)->generate(new AIRequest('Hello'), 'test');
            $this->fail('Bad credentials succeeded.');
        } catch (AIException $exception) {
            $this->assertSame('authentication', $exception->reason);
            $this->assertStringNotContainsString('secret response', $exception->getMessage());
        }

        $response = app(AIManager::class)->generate(new AIRequest('Hello'), 'test');
        $this->assertSame('Recovered.', $response->text);

        try {
            app(AIManager::class)->chatWithTools(new AIToolRequest([new AIMessage('user', 'Use a tool')], []));
            $this->fail('Unsupported tool calling succeeded.');
        } catch (AIException $exception) {
            $this->assertSame('unsupported_tools', $exception->reason);
        }

        Log::shouldHaveReceived('warning')->with('Azure Responses request failed', Mockery::on(fn (array $context): bool => $context['http_status'] === 401
            && $context['azure_error_code'] === 'invalid_api_key'
            && $context['azure_error_type'] === 'authentication_error'
            && $context['request_stage'] === 'content_generation'
            && $context['endpoint_route'] === '/api/projects/triparo/openai/v1/responses'
            && $context['deployment'] === 'triparo-gpt'
            && ! str_contains($context['safe_message'], 'fake-azure-key')));
        Http::assertSentCount(4);
    }
}
