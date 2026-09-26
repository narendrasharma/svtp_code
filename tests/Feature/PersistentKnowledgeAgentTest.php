<?php

namespace Tests\Feature;

use App\AI\Contracts\AIToolCallingProviderInterface;
use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\DTOs\AIExecutionContext;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\AIToolCall;
use App\AI\DTOs\AIToolRequest;
use App\AI\DTOs\AIToolResponse;
use App\AI\DTOs\ProviderCapabilities;
use App\AI\Support\AIException;
use App\AI\Support\AIProviderRegistry;
use App\AI\Support\AIToolAction;
use App\AI\Support\EmbeddingProviderRegistry;
use App\AI\Support\KnowledgeIndexingService;
use App\AI\Tools\SearchKnowledgeTool;
use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
use App\Models\Page;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PersistentKnowledgeAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Http::preventStrayRequests();
        Setting::setValue('ai.enabled', '1');
        Setting::setValue('ai.provider', 'openai');
        Setting::setValue('ai.knowledge.enabled', '1');
        config()->set('services.ai.providers.openai.key', 'test-key');
    }

    public function test_conversations_are_strictly_owned_and_deletion_cascades(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Private question']);
        $conversation->toolEvents()->create(['tool_name' => 'search_tours', 'succeeded' => true, 'duration_ms' => 3, 'result_count' => 1, 'created_at' => now()]);

        $this->actingAs($other)->getJson(route('admin.ai-assistant.conversations.show', $conversation))->assertNotFound();
        $this->patchJson(route('admin.ai-assistant.conversations.rename', $conversation), ['title' => 'Stolen'])->assertNotFound();
        $this->deleteJson(route('admin.ai-assistant.conversations.destroy', $conversation))->assertNotFound();

        $this->actingAs($owner)->deleteJson(route('admin.ai-assistant.conversations.destroy', $conversation))->assertOk();
        $this->assertDatabaseMissing('ai_messages', ['ai_conversation_id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_tool_events', ['ai_conversation_id' => $conversation->id]);
    }

    public function test_existing_conversation_uses_only_bounded_server_history(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        foreach (range(1, 12) as $number) {
            $conversation->messages()->create(['role' => $number % 2 ? 'user' : 'assistant', 'content' => "Message {$number}"]);
        }
        $provider = $this->fakeChat([
            new AIToolResponse('', [new AIToolCall('call-1', 'search_tours', ['query' => 'Agra'])], 'openai', 'test-model'),
            new AIToolResponse('Found a tour.', [], 'openai', 'test-model'),
        ]);

        $this->actingAs($user)->postJson(route('admin.ai-assistant.message'), [
            'conversation_id' => $conversation->id, 'question' => 'What about Agra?',
            'history' => [['role' => 'user', 'content' => 'Malicious browser history']],
        ])->assertOk()->assertJsonPath('conversation.id', $conversation->id);

        $contents = array_map(fn ($message) => $message->content, $provider->requests[0]->messages);
        $this->assertCount(8, $contents);
        $this->assertNotContains('Message 1', $contents);
        $this->assertNotContains('Malicious browser history', $contents);
        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $conversation->id, 'content' => 'Found a tour.']);
        $this->assertDatabaseHas('ai_tool_events', ['ai_conversation_id' => $conversation->id, 'tool_name' => 'search_tours']);
    }

    public function test_indexing_is_bounded_and_unchanged_content_is_not_reembedded(): void
    {
        $embedding = $this->fakeEmbedding();
        $document = AiKnowledgeDocument::factory()->create(['title' => 'Hotel Check-in', 'content' => str_repeat('Check-in policy. ', 110)]);
        $indexer = app(KnowledgeIndexingService::class);
        $first = $indexer->index($document);
        $this->assertTrue($first['indexed']);
        $this->assertGreaterThan(1, $first['chunks']);
        $this->assertLessThanOrEqual(20, $first['chunks']);
        $this->assertSame($first['chunks'], $embedding->calls);
        $this->assertFalse($indexer->index($document->fresh())['indexed']);
        $this->assertSame($first['chunks'], $embedding->calls);
    }

    public function test_retrieval_returns_relevant_bounded_chunks_and_hides_internal_documents(): void
    {
        $this->fakeEmbedding();
        $indexer = app(KnowledgeIndexingService::class);
        $internal = AiKnowledgeDocument::factory()->create(['title' => 'Private Check-in', 'content' => 'Check-in at 2 PM.', 'visibility' => 'internal']);
        $public = AiKnowledgeDocument::factory()->create(['title' => 'Public Check-in', 'content' => 'Check-in at 3 PM.', 'visibility' => 'public']);
        $indexer->index($internal);
        $indexer->index($public);
        $tool = app(SearchKnowledgeTool::class);
        $this->assertSame(AIToolAction::Read, $tool->action());
        $publicResult = $tool->execute(new AIExecutionContext(1, 'admin', ['ai.assistant.use']), ['query' => 'check-in', 'limit' => 1]);
        $this->assertCount(1, $publicResult['items']);
        $this->assertSame('Public Check-in', $publicResult['items'][0]['title']);
        $internalResult = $tool->execute(new AIExecutionContext(1, 'admin', ['ai.assistant.use', 'ai.knowledge.manage']), ['query' => 'Private Check-in']);
        $this->assertContains('Private Check-in', array_column($internalResult['items'], 'title'));
    }

    public function test_staff_without_knowledge_permission_cannot_manage_articles(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $role = Role::create(['name' => 'assistant-only', 'guard_name' => 'web']);
        $role->givePermissionTo('ai.assistant.use');
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staff->fresh())->get(route('admin.ai-assistant.knowledge.index'))->assertForbidden();
        $this->post(route('admin.ai-assistant.knowledge.store'), ['title' => 'Private', 'content' => 'Secret'])->assertForbidden();
        $this->assertDatabaseCount('ai_knowledge_documents', 0);
    }

    public function test_import_indexes_only_active_public_sources_and_hides_deactivated_pages(): void
    {
        $this->fakeEmbedding();
        $active = Page::factory()->create(['title' => 'Check-in Guide', 'content' => '<p>Check-in at 3 PM.</p>', 'is_active' => true]);
        Page::factory()->create(['title' => 'Hidden Check-in', 'content' => '<p>Check-in at noon.</p>', 'is_active' => false]);
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('admin.ai-assistant.knowledge.import'), ['source_type' => 'page'])->assertRedirect();
        $this->assertDatabaseCount('ai_knowledge_documents', 1);
        $document = AiKnowledgeDocument::firstOrFail();
        $this->assertSame('public', $document->visibility);
        $this->assertSame('active', $document->status);
        $context = new AIExecutionContext($user->id, 'admin', ['ai.assistant.use', 'ai.knowledge.manage']);
        $this->assertCount(1, app(SearchKnowledgeTool::class)->execute($context, ['query' => 'Check-in'])['items']);

        $active->update(['is_active' => false]);
        $this->assertSame([], app(SearchKnowledgeTool::class)->execute($context, ['query' => 'Check-in'])['items']);
    }

    public function test_agent_combines_marketplace_and_knowledge_evidence_without_write_tools(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', true);
        $this->fakeEmbedding();
        $document = AiKnowledgeDocument::factory()->create(['title' => 'Hotel Check-in Policy', 'content' => 'Check-in is at 2 PM.']);
        app(KnowledgeIndexingService::class)->index($document);
        $provider = $this->fakeChat([
            new AIToolResponse('', [
                new AIToolCall('call-1', 'search_hotel_bookings', ['limit' => 1]),
                new AIToolCall('call-2', 'search_knowledge', ['query' => 'Hotel Check-in Policy']),
            ], 'openai', 'test-model'),
            new AIToolResponse('There are no arrivals; check-in is at 2 PM.', [], 'openai', 'test-model'),
        ]);
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->postJson(route('admin.ai-assistant.message'), ['question' => 'Arrivals and check-in policy?'])
            ->assertOk()->assertJsonPath('sources.0.title', 'Hotel Check-in Policy')
            ->assertJsonPath('tools_used.1', 'search_knowledge');

        $toolNames = array_column($provider->requests[0]->tools, 'name');
        $this->assertContains('search_knowledge', $toolNames);
        $this->assertNotContains('cancel_booking', $toolNames);
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_knowledge', 'succeeded' => true]);
    }

    public function test_embedding_429_does_not_break_an_operational_tour_answer(): void
    {
        $this->fakeGeminiEmbeddingLimit();
        TourPackage::factory()->approved()->create(['title' => 'Agra Heritage Walk']);
        $this->fakeChat([
            new AIToolResponse('', [
                new AIToolCall('tour-1', 'search_tours', ['query' => 'Agra']),
                new AIToolCall('knowledge-1', 'search_knowledge', ['query' => 'Agra policy']),
            ], 'openai', 'test-model'),
            new AIToolResponse('Agra Heritage Walk is listed.', [], 'openai', 'test-model'),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Find an active tour in Agra.'])
            ->assertOk()->assertJsonPath('tools_used.0', 'search_tours')
            ->assertJsonPath('tools_used.1', 'search_knowledge')
            ->assertSee('Agra Heritage Walk is listed.')
            ->assertSee('knowledge portion could not be retrieved');

        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_tours', 'succeeded' => true]);
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_knowledge', 'succeeded' => false]);
        Http::assertSentCount(1);
    }

    public function test_knowledge_only_question_reports_embedding_limit_without_hallucinating(): void
    {
        $this->fakeGeminiEmbeddingLimit();
        $this->fakeChat([
            new AIToolResponse('', [new AIToolCall('knowledge-1', 'search_knowledge', ['query' => 'Hotel check-in policy'])], 'openai', 'test-model'),
            new AIToolResponse('The check-in policy is 2 PM.', [], 'openai', 'test-model'),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'What is our hotel check-in policy?'])
            ->assertOk()->assertJsonPath('sources', []);

        $this->assertSame(
            'Knowledge search is temporarily unavailable because the embedding provider rate limit was reached. Please try again later.',
            $response->json('answer'),
        );
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_knowledge', 'succeeded' => false]);
        Http::assertSentCount(1);
    }

    public function test_mixed_booking_and_knowledge_question_keeps_booking_evidence(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', true);
        $this->fakeGeminiEmbeddingLimit();
        $this->fakeChat([
            new AIToolResponse('', [
                new AIToolCall('booking-1', 'search_hotel_bookings', ['limit' => 1]),
                new AIToolCall('knowledge-1', 'search_knowledge', ['query' => 'Hotel check-in policy']),
            ], 'openai', 'test-model'),
            new AIToolResponse('No hotel arrivals were found.', [], 'openai', 'test-model'),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Show hotel arrivals and our check-in policy.'])
            ->assertOk()->assertJsonPath('tools_used.0', 'search_hotel_bookings');

        $this->assertStringContainsString('No hotel arrivals were found.', $response->json('answer'));
        $this->assertStringContainsString('knowledge portion could not be retrieved', $response->json('answer'));
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_hotel_bookings', 'succeeded' => true]);
        $this->assertDatabaseHas('ai_tool_events', ['tool_name' => 'search_knowledge', 'succeeded' => false]);
        Http::assertSentCount(1);
    }

    public function test_chat_rate_limit_identifies_the_assistant_not_knowledge(): void
    {
        Setting::setValue('ai.provider', 'gemini');
        config()->set('services.ai.providers.gemini.key', 'test-gemini-key');
        app(AIProviderRegistry::class)->register(new class implements AIToolCallingProviderInterface
        {
            public function key(): string
            {
                return 'gemini';
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(['text_generation', 'tool_calling']);
            }

            public function generate(AIRequest $request): AIResponse
            {
                throw AIException::rateLimited();
            }

            public function chatWithTools(AIToolRequest $request): AIToolResponse
            {
                throw AIException::rateLimited();
            }
        });

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Find Agra tours.'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Gemini Assistant rate limit reached. Try again later.');
    }

    private function fakeGeminiEmbeddingLimit(): void
    {
        Setting::setValue('ai.embedding.provider', 'gemini');
        Setting::setValue('ai.embedding.model', 'gemini-embedding-2');
        config()->set('services.ai.providers.gemini.key', 'test-gemini-key');
        $document = AiKnowledgeDocument::factory()->create([
            'title' => 'Hotel check-in policy', 'content' => 'Check-in at 2 PM.', 'status' => 'active', 'visibility' => 'public',
        ]);
        $document->chunks()->create([
            'chunk_index' => 0, 'content' => 'Hotel check-in policy', 'embedding' => [1.0, 0.0],
            'embedding_provider' => 'gemini', 'embedding_model' => 'gemini-embedding-2',
            'content_hash' => hash('sha256', 'Hotel check-in policy'),
        ]);
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
            'error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded'],
        ], 429)]);
    }

    private function fakeEmbedding(): EmbeddingProviderInterface
    {
        $provider = new class implements EmbeddingProviderInterface
        {
            public int $calls = 0;

            public function key(): string
            {
                return 'openai';
            }

            public function embed(string $text, string $model, string $purpose = 'document', ?string $title = null): array
            {
                $this->calls++;
                $lower = mb_strtolower($text);

                return [str_contains($lower, 'check-in') ? 1.0 : 0.0, str_contains($lower, 'cancellation') ? 1.0 : 0.0];
            }
        };
        app(EmbeddingProviderRegistry::class)->register($provider);

        return $provider;
    }

    /** @param list<AIToolResponse> $responses */
    private function fakeChat(array $responses): AIToolCallingProviderInterface
    {
        $provider = new class($responses) implements AIToolCallingProviderInterface
        {
            public array $requests = [];

            public function __construct(private array $responses) {}

            public function key(): string
            {
                return 'openai';
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(['text_generation', 'tool_calling']);
            }

            public function generate(AIRequest $request): AIResponse
            {
                throw new \RuntimeException('Unexpected generation');
            }

            public function chatWithTools(AIToolRequest $request): AIToolResponse
            {
                $this->requests[] = $request;

                return array_shift($this->responses);
            }
        };
        app(AIProviderRegistry::class)->register($provider);

        return $provider;
    }
}
