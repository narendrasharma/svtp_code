<?php

namespace Tests\Feature;

use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\DTOs\AIMessage;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIToolRequest;
use App\AI\Support\AIException;
use App\AI\Support\AIManager;
use App\AI\Support\EmbeddingProviderRegistry;
use App\AI\Support\KnowledgeIndexingService;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GeminiAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Http::preventStrayRequests();
        Setting::setValue('ai.enabled', '1');
        Setting::setValue('ai.provider', 'gemini');
        config()->set('services.ai.providers.gemini.key', 'test-gemini-key');
    }

    public function test_supported_gemini_model_advertises_tools_and_old_model_does_not(): void
    {
        $this->assertSame('gemini-3.8-flash', config('services.ai.providers.gemini.model'));
        $this->assertTrue(app(AIManager::class)->capabilities()->supports('tool_calling'));

        Setting::setValue('ai.model', 'gemini-1.5-flash');
        $this->assertFalse(app(AIManager::class)->capabilities()->supports('tool_calling'));
        $this->assertTrue(app(AIManager::class)->capabilities()->supports('text_generation'));
    }

    public function test_function_declarations_multiple_calls_and_signed_results_roundtrip(): void
    {
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push($this->callsResponse([
                ['functionCall' => ['id' => 'fc-1', 'name' => 'search_tours', 'args' => ['query' => 'Agra']], 'thoughtSignature' => 'opaque-signed-part'],
                ['functionCall' => ['id' => 'fc-2', 'name' => 'search_knowledge', 'args' => ['query' => 'Check-in']]],
            ]))
            ->push($this->callsResponse([['text' => 'Agra tours were found. The policy is indexed.']]))]);
        $tools = [
            ['name' => 'search_tours', 'description' => 'Find tours', 'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'additionalProperties' => false]],
            ['name' => 'search_knowledge', 'description' => 'Find policies', 'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']]],
        ];
        $messages = [new AIMessage('system', 'Use tools for Triparo facts.'), new AIMessage('user', 'Agra tours and check-in policy')];
        $first = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, $tools, true));

        $this->assertSame(['fc-1', 'fc-2'], array_map(fn ($call): string => $call->id, $first->toolCalls));
        $this->assertSame('search_knowledge', $first->toolCalls[1]->name);
        $this->assertNotNull($first->providerState);

        $messages[] = new AIMessage('assistant', $first->text, $first->toolCalls, providerState: $first->providerState);
        $messages[] = new AIMessage('tool', '{"items":[{"title":"Agra Tour"}]}', toolCallId: 'fc-1');
        $messages[] = new AIMessage('tool', '{"items":[{"title":"Check-in Policy"}]}', toolCallId: 'fc-2');
        $second = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, $tools));

        $this->assertSame('Agra tours were found. The policy is indexed.', $second->text);
        Http::assertSent(fn ($request): bool => $request['toolConfig']['functionCallingConfig']['mode'] === 'ANY'
            && $request['tools'][0]['functionDeclarations'][0]['name'] === 'search_tours'
            && $request['generationConfig']['thinkingConfig']['thinkingLevel'] === 'low'
            && ! isset($request['tools'][0]['functionDeclarations'][0]['parameters']['additionalProperties']));
        Http::assertSent(fn ($request): bool => isset($request['contents'][1]['parts'][0]['thoughtSignature'])
            && $request['contents'][1]['parts'][0]['thoughtSignature'] === 'opaque-signed-part'
            && $request['contents'][2]['parts'][0]['functionResponse']['id'] === 'fc-1'
            && $request['contents'][2]['parts'][1]['functionResponse']['id'] === 'fc-2'
            && $request['contents'][2]['parts'][1]['functionResponse']['response']['items'][0]['title'] === 'Check-in Policy');
        Http::assertSentCount(2);
    }

    public function test_gemini_agent_combines_marketplace_read_and_openai_backed_knowledge(): void
    {
        Setting::setValue('ai.knowledge.enabled', '1');
        config()->set('services.ai.providers.openai.key', 'embedding-key');
        $this->fakeEmbedding();
        $document = AiKnowledgeDocument::factory()->create(['title' => 'Hotel Check-in Policy', 'content' => 'Check-in is at 2 PM.']);
        app(KnowledgeIndexingService::class)->index($document);
        TourPackage::factory()->approved()->create(['title' => 'Agra Heritage Walk']);
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push($this->callsResponse([
                ['functionCall' => ['id' => 'read-1', 'name' => 'search_tours', 'args' => ['query' => 'Agra']], 'thoughtSignature' => 'signed-read'],
                ['functionCall' => ['id' => 'read-2', 'name' => 'search_knowledge', 'args' => ['query' => 'Hotel Check-in Policy']]],
            ]))
            ->push($this->callsResponse([['text' => 'Agra Heritage Walk is listed. Check-in is at 2 PM.']]))]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Show Agra tours and our hotel check-in policy'])
            ->assertOk()->assertJsonPath('answer', 'Agra Heritage Walk is listed. Check-in is at 2 PM.')
            ->assertJsonPath('sources.0.title', 'Hotel Check-in Policy')
            ->assertJsonPath('tools_used.1', 'search_knowledge');

        $this->assertSame('gemini', AiConversation::firstOrFail()->provider);
        Setting::setValue('ai.provider', 'openai');
        $this->getJson(route('admin.ai-assistant.conversations.show', AiConversation::firstOrFail()->id))
            ->assertOk()->assertJsonPath('messages.1.content', 'Agra Heritage Walk is listed. Check-in is at 2 PM.');
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_tours', 'succeeded' => true]);
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_knowledge', 'succeeded' => true]);
        Http::assertSentCount(2);
    }

    public function test_supported_gemini_25_call_without_remote_id_roundtrips_without_id(): void
    {
        Setting::setValue('ai.model', 'gemini-2.5-flash');
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push($this->callsResponse([['functionCall' => ['name' => 'search_tours', 'args' => ['query' => 'Agra']]]]))
            ->push($this->callsResponse([['text' => 'Found one tour.']]))]);
        $messages = [new AIMessage('user', 'Find Agra tours')];
        $first = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, $this->simpleTool()));

        $this->assertSame('gemini-local-0', $first->toolCalls[0]->id);
        $messages[] = new AIMessage('assistant', $first->text, $first->toolCalls, providerState: $first->providerState);
        $messages[] = new AIMessage('tool', '{"items":[]}', toolCallId: $first->toolCalls[0]->id);
        $second = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, $this->simpleTool()));

        $this->assertSame('Found one tour.', $second->text);
        Http::assertSent(fn ($request): bool => isset($request['contents'][2]['parts'][0]['functionResponse'])
            && ! isset($request['contents'][2]['parts'][0]['functionResponse']['id'])
            && $request['contents'][2]['parts'][0]['functionResponse']['name'] === 'search_tours');
        Http::assertSentCount(2);
    }

    public function test_gemini_write_call_only_proposes_and_actions_off_hides_write_tools(): void
    {
        Setting::setValue('ai.agent_actions.enabled', '1');
        $tour = TourPackage::factory()->approved()->create(['title' => 'Agra Tour', 'is_featured' => false]);
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push($this->callsResponse([['functionCall' => [
                'id' => 'write-1', 'name' => 'set_tour_featured', 'args' => ['tour_id' => $tour->id, 'featured' => true],
            ], 'thoughtSignature' => 'signed-write']]))
            ->push($this->callsResponse([['functionCall' => [
                'id' => 'read-1', 'name' => 'search_tours', 'args' => ['query' => 'Agra'],
            ], 'thoughtSignature' => 'signed-read']]))
            ->push($this->callsResponse([['text' => 'Found Agra Tour.']]))]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Make it featured'])
            ->assertOk()->assertJsonPath('proposal.status', 'pending')
            ->assertJsonPath('proposal.tool_name', 'set_tour_featured');
        $this->assertFalse($tour->fresh()->is_featured);
        $this->assertDatabaseCount('ai_action_proposals', 1);

        Setting::setValue('ai.agent_actions.enabled', '0');
        $this->postJson(route('admin.ai-assistant.message'), ['question' => 'Find Agra tours'])
            ->assertOk()->assertJsonPath('answer', 'Found Agra Tour.');
        Http::assertSent(fn ($request): bool => ($request['contents'][0]['parts'][0]['text'] ?? null) === 'Find Agra tours'
            && ! in_array('set_tour_featured', array_column($request['tools'][0]['functionDeclarations'], 'name'), true));
        $this->assertFalse($tour->fresh()->is_featured);
        $this->assertSame('pending', AiActionProposal::firstOrFail()->status);
        Http::assertSentCount(3);
    }

    public function test_content_generation_still_works_and_http_failures_are_safe(): void
    {
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push($this->callsResponse([['text' => 'A Gemini draft.']]))
            ->push(['error' => ['message' => 'Secret provider details']], 429)
            ->push($this->callsResponse([['functionCall' => ['id' => 'unsigned-1', 'name' => 'search_tours', 'args' => []]]]))
            ->push($this->callsResponse([
                ['functionCall' => ['id' => 'duplicate', 'name' => 'search_tours', 'args' => []], 'thoughtSignature' => 'signed'],
                ['functionCall' => ['id' => 'duplicate', 'name' => 'search_tours', 'args' => []]],
            ]))]);
        $draft = app(AIManager::class)->generate(new AIRequest('Tour notes', 'Write a draft.', temperature: 0.5, maxTokens: 300), 'content_copilot.tour.improve');

        $this->assertSame('A Gemini draft.', $draft->text);
        Http::assertSent(fn ($request): bool => ($request['systemInstruction']['parts'][0]['text'] ?? null) === 'Write a draft.'
            && ! isset($request['generationConfig']['temperature'])
            && $request['generationConfig']['thinkingConfig']['thinkingLevel'] === 'low'
            && $request['generationConfig']['maxOutputTokens'] === 1024);

        try {
            app(AIManager::class)->chatWithTools(new AIToolRequest([new AIMessage('user', 'Find tours')], $this->simpleTool()));
            $this->fail('A rate-limited provider call succeeded.');
        } catch (AIException $exception) {
            $this->assertSame('rate_limited', $exception->reason);
            $this->assertSame(429, $exception->httpStatus);
            $this->assertStringNotContainsString('Secret provider details', $exception->getMessage());
        }

        Setting::setValue('ai.model', 'gemini-1.5-flash');

        try {
            app(AIManager::class)->chatWithTools(new AIToolRequest([new AIMessage('user', 'Find tours')], $this->simpleTool()));
            $this->fail('An unsupported model used the assistant.');
        } catch (AIException $exception) {
            $this->assertSame('unsupported_tools', $exception->reason);
        }

        Setting::setValue('ai.model', null);

        try {
            app(AIManager::class)->chatWithTools(new AIToolRequest([new AIMessage('user', 'Find tours')], $this->simpleTool()));
            $this->fail('An unsigned Gemini 3 tool call was accepted.');
        } catch (AIException $exception) {
            $this->assertSame('invalid_response', $exception->reason);
        }

        try {
            app(AIManager::class)->chatWithTools(new AIToolRequest([new AIMessage('user', 'Find tours')], $this->simpleTool()));
            $this->fail('Duplicate Gemini call IDs were accepted.');
        } catch (AIException $exception) {
            $this->assertSame('invalid_response', $exception->reason);
        }

        Http::assertSentCount(4);
    }

    public function test_live_high_demand_error_is_retried_once_and_stays_safe(): void
    {
        $error = ['error' => [
            'code' => 503,
            'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.',
            'status' => 'UNAVAILABLE',
        ]];
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push($error, 503)
            ->push($error, 503)]);

        try {
            app(AIManager::class)->chatWithTools(new AIToolRequest([new AIMessage('user', 'Use the tool.')], $this->simpleTool(), true));
            $this->fail('An overloaded provider returned a tool response.');
        } catch (AIException $exception) {
            $this->assertSame('unavailable', $exception->reason);
            $this->assertStringNotContainsString('high demand', $exception->getMessage());
        }

        Http::assertSentCount(2);
    }

    public function test_live_high_demand_error_retries_a_function_declaration(): void
    {
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push(['error' => [
                'code' => 503,
                'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.',
                'status' => 'UNAVAILABLE',
            ]], 503)
            ->push($this->callsResponse([['functionCall' => [
                'id' => 'test-1', 'name' => 'search_tours', 'args' => ['query' => 'Agra'],
            ], 'thoughtSignature' => 'opaque-signed-part']]))]);

        $response = app(AIManager::class)->chatWithTools(new AIToolRequest([new AIMessage('user', 'Find Agra tours.')], $this->simpleTool(), true));

        $this->assertSame('search_tours', $response->toolCalls[0]->name);
        $this->assertNotNull($response->providerState);
        Http::assertSentCount(2);
    }

    private function callsResponse(array $parts): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => $parts], 'finishReason' => 'STOP']], 'modelVersion' => 'gemini-3.8-flash'];
    }

    private function simpleTool(): array
    {
        return [['name' => 'search_tours', 'description' => 'Find tours', 'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]]];
    }

    private function fakeEmbedding(): void
    {
        app(EmbeddingProviderRegistry::class)->register(new class implements EmbeddingProviderInterface
        {
            public function key(): string
            {
                return 'openai';
            }

            public function embed(string $text, string $model, string $purpose = 'document', ?string $title = null): array
            {
                return [str_contains(mb_strtolower($text), 'check-in') ? 1.0 : 0.0, 0.0];
            }
        });
    }
}
