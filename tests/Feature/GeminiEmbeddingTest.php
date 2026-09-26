<?php

namespace Tests\Feature;

use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\AISettings;
use App\AI\Support\DatabaseVectorStore;
use App\AI\Support\EmbeddingManager;
use App\AI\Support\KnowledgeIndexingService;
use App\AI\Tools\SearchKnowledgeTool;
use App\Models\AiKnowledgeDocument;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GeminiEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Http::preventStrayRequests();
        Setting::setValue('ai.enabled', '1');
        Setting::setValue('ai.knowledge.enabled', '1');
        Setting::setValue('ai.embedding.provider', 'gemini');
        Setting::setValue('ai.embedding.model', 'gemini-embedding-2');
        config()->set('services.ai.providers.gemini.key', 'test-gemini-key');
    }

    public function test_gemini_embedding_uses_existing_credential_and_normalizes_vector(): void
    {
        app(AISettings::class)->saveCredential('gemini', 'saved-gemini-key');
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::response($this->geminiVector())]);

        $vector = app(EmbeddingManager::class)->embed('Hotel check-in policy.', 'document', 'Hotel Policy');

        $this->assertCount(768, $vector);
        $this->assertSame(1.0, $vector[0]);
        Http::assertSent(fn ($request): bool => $request->hasHeader('x-goog-api-key', 'saved-gemini-key')
            && str_contains($request->url(), 'gemini-embedding-2:embedContent')
            && $request['outputDimensionality'] === 768
            && $request['content']['parts'][0]['text'] === 'title: Hotel Policy | text: Hotel check-in policy.'
            && ! isset($request['taskType']));
        Http::assertSentCount(1);
    }

    public function test_provider_and_model_switch_reindexes_unchanged_content_and_preserves_openai(): void
    {
        config()->set('services.ai.providers.openai.key', 'test-openai-key');
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => array_fill(0, 256, 0.5)]]]),
            'generativelanguage.googleapis.com/v1beta/models/*' => Http::response($this->geminiVector()),
        ]);
        $document = AiKnowledgeDocument::factory()->create(['title' => 'Check-in', 'content' => 'Check-in is at 2 PM.']);
        $indexer = app(KnowledgeIndexingService::class);
        Setting::setValue('ai.embedding.provider', 'openai');
        Setting::setValue('ai.embedding.model', 'text-embedding-3-small');

        $this->assertTrue($indexer->index($document)['indexed']);
        $this->assertSame('openai', $document->fresh()->chunks()->firstOrFail()->embedding_provider);
        $this->assertCount(256, $document->fresh()->chunks()->firstOrFail()->embedding);

        Setting::setValue('ai.embedding.provider', 'gemini');
        Setting::setValue('ai.embedding.model', 'gemini-embedding-2');
        $this->assertTrue($indexer->index($document->fresh())['indexed']);
        $this->assertSame('gemini', $document->fresh()->chunks()->firstOrFail()->embedding_provider);
        $this->assertCount(768, $document->fresh()->chunks()->firstOrFail()->embedding);
        $this->assertFalse($indexer->index($document->fresh())['indexed']);

        Setting::setValue('ai.embedding.model', 'gemini-embedding-001');
        $this->assertTrue($indexer->index($document->fresh())['indexed']);
        $this->assertSame('gemini-embedding-001', $document->fresh()->chunks()->firstOrFail()->embedding_model);
        Http::assertSentCount(3);
    }

    public function test_mismatched_vector_dimensions_cannot_be_rescued_by_lexical_match(): void
    {
        $document = AiKnowledgeDocument::factory()->create([
            'title' => 'Hotel check-in policy', 'content' => 'Check-in is at 2 PM.', 'status' => 'active',
        ]);
        $document->chunks()->create([
            'chunk_index' => 0, 'content' => 'Hotel check-in policy', 'embedding' => [1.0, 0.0],
            'embedding_provider' => 'gemini', 'embedding_model' => 'gemini-embedding-2',
            'content_hash' => hash('sha256', 'Hotel check-in policy'),
        ]);

        $results = app(DatabaseVectorStore::class)->search(
            array_fill(0, 768, 1.0), 'gemini', 'gemini-embedding-2', 'Hotel check-in policy',
            new AIExecutionContext(1, 'admin', ['ai.assistant.use', 'ai.knowledge.manage']), 5,
        );

        $this->assertSame([], $results);
    }

    public function test_search_knowledge_uses_gemini_without_openai(): void
    {
        config()->set('services.ai.providers.openai.key', null);
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::response($this->geminiVector())]);
        $document = AiKnowledgeDocument::factory()->create([
            'title' => 'Hotel Check-in Policy', 'content' => 'Check-in is at 2 PM.',
        ]);
        app(KnowledgeIndexingService::class)->index($document);

        $result = app(SearchKnowledgeTool::class)->execute(
            new AIExecutionContext(1, 'admin', ['ai.assistant.use', 'ai.knowledge.manage']),
            ['query' => 'hotel check-in'],
        );

        $this->assertSame('Hotel Check-in Policy', $result['items'][0]['title']);
        Http::assertSent(fn ($request): bool => str_contains($request['content']['parts'][0]['text'], 'task: search result | query: hotel check-in'));
        Http::assertSentCount(2);
    }

    public function test_settings_expose_independent_gemini_embedding_choice_and_reindex_status_without_secret(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $secret = 'saved-gemini-secret';
        $this->actingAs($admin)->post(route('admin.settings.ai.update'), [
            'ai_enabled' => true, 'ai_provider' => 'gemini', 'ai_model' => 'gemini-3.8-flash',
            'api_key' => $secret, 'knowledge_enabled' => true, 'agent_actions_enabled' => false,
            'embedding_provider' => 'gemini', 'embedding_model' => 'gemini-embedding-2',
        ])->assertRedirect();

        $stored = Setting::getValue('ai.gemini.key');
        $this->assertSame($secret, Crypt::decryptString($stored));
        $this->assertDatabaseMissing('settings', ['key' => 'ai.openai.key']);
        $page = $this->get(route('admin.settings.index'));
        $page->assertOk();
        $ai = $page->viewData('page')['props']['settings']['ai'];
        $this->assertSame('gemini', $ai['embedding_provider']);
        $this->assertContains('gemini', $ai['embedding_providers']);
        $this->assertTrue($ai['embedding_credential_configured']);
        $this->assertStringNotContainsString($secret, $page->getContent());
        $this->assertStringNotContainsString($stored, $page->getContent());

        $document = AiKnowledgeDocument::factory()->create(['status' => 'active', 'last_indexed_at' => now()]);
        $document->chunks()->create([
            'chunk_index' => 0, 'content' => 'Old index', 'embedding' => [1.0],
            'embedding_provider' => 'openai', 'embedding_model' => 'text-embedding-3-small',
            'content_hash' => hash('sha256', 'Old index'),
        ]);
        $knowledge = $this->get(route('admin.ai-assistant.knowledge.index'));
        $knowledge->assertOk();
        $this->assertSame(1, $knowledge->viewData('page')['props']['embeddingStatus']['needs_reindex']);
        $this->assertSame(0, $knowledge->viewData('page')['props']['documents'][0]['current_index_chunks_count']);
    }

    public function test_transient_gemini_embedding_failure_retries_once(): void
    {
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/*' => Http::sequence()
            ->push(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'High demand']], 503)
            ->push($this->geminiVector())]);

        $this->assertCount(768, app(EmbeddingManager::class)->embed('Triparo embedding connectivity test.', 'query'));
        Http::assertSentCount(2);
    }

    private function geminiVector(): array
    {
        $values = array_fill(0, 768, 0.0);
        $values[0] = 1.0;

        return ['embedding' => ['values' => $values]];
    }
}
