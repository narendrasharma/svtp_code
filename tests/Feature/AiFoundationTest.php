<?php

namespace Tests\Feature;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ProviderCapabilities;
use App\AI\Support\AIManager;
use App\AI\Support\AIProviderRegistry;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_manager_resolves_configured_provider_and_model(): void
    {
        Setting::setValue('ai.enabled', '1');
        Setting::setValue('ai.provider', 'openai');
        Setting::setValue('ai.model', 'chosen-model');
        config()->set('services.ai.providers.openai.key', 'test-key');
        config()->set('services.ai.providers.gemini.key', 'gemini-test-key');
        config()->set('services.ai.providers.claude.key', 'claude-test-key');
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'model' => 'chosen-model',
                'choices' => [['message' => ['content' => 'A helpful draft'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12],
            ]),
            'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'A Gemini draft']]]]],
            ]),
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['text' => 'A Claude draft']],
            ]),
        ]);

        $response = app(AIManager::class)->generate(new AIRequest('Trip notes'), 'itinerary_draft');

        $this->assertSame('A helpful draft', $response->text);
        $this->assertSame('openai', $response->provider);
        $this->assertSame('chosen-model', $response->model);
        $this->assertSame(12, $response->usage['total_tokens']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request['model'] === 'chosen-model');

        Setting::setValue('ai.provider', 'gemini');
        Setting::setValue('ai.model', null);
        $gemini = app(AIManager::class)->generate(new AIRequest('Trip notes'), 'itinerary_draft');
        $this->assertSame('A Gemini draft', $gemini->text);
        $this->assertSame('gemini-3.8-flash', $gemini->model);

        Setting::setValue('ai.provider', 'claude');
        $claude = app(AIManager::class)->generate(new AIRequest('Trip notes'), 'itinerary_draft');
        $this->assertSame('A Claude draft', $claude->text);
        $this->assertSame('claude-sonnet-4-6', $claude->model);
    }

    public function test_disabled_and_unconfigured_ai_fail_safely_without_external_calls(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('ai.enabled', '0');

        $this->actingAs($admin)->postJson(route('admin.packages.ai-draft'), ['notes' => 'Mathura'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI is disabled.');

        Setting::setValue('ai.enabled', '1');
        config()->set('services.ai.providers.openai.key', null);

        $this->postJson(route('admin.packages.ai-draft'), ['notes' => 'Mathura'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI is not configured.');
    }

    public function test_itinerary_draft_uses_provider_adapter_and_remains_a_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('ai.enabled', '1');
        config()->set('services.ai.providers.openai.key', 'test-key');
        Http::preventStrayRequests();
        $provider = new class implements AIProviderInterface
        {
            public ?AIRequest $lastRequest = null;

            public function key(): string
            {
                return 'openai';
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(['text_generation']);
            }

            public function generate(AIRequest $request): AIResponse
            {
                $this->lastRequest = $request;

                return new AIResponse('Day 1: Visit Mathura.', 'openai', $request->model);
            }
        };
        app(AIProviderRegistry::class)->register($provider);

        $this->actingAs($admin)->postJson(route('admin.packages.ai-draft'), ['notes' => 'Mathura'])
            ->assertOk()
            ->assertJsonPath('draft', 'Day 1: Visit Mathura.');

        $this->assertStringContainsString('Mathura', $provider->lastRequest->userContent);
        $this->assertSame('gpt-4o-mini', $provider->lastRequest->model);
        $this->assertDatabaseCount('tour_packages', 0);
    }

    public function test_ai_settings_encrypt_secret_and_never_expose_it_in_page_or_validation_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $secret = 'sk-test-'.str_repeat('x', 180);

        $this->actingAs($admin)->post(route('admin.settings.ai.update'), [
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'api_key' => $secret,
        ])->assertRedirect();

        $stored = Setting::getValue('ai.openai.key');
        $this->assertNotSame($secret, $stored);
        $this->assertSame($secret, Crypt::decryptString($stored));

        $page = $this->get(route('admin.settings.index'));
        $page->assertOk();
        $this->assertTrue($page->viewData('page')['props']['settings']['ai']['credential_configured']);
        $this->assertStringNotContainsString($secret, $page->getContent());
        $this->assertStringNotContainsString($stored, $page->getContent());

        $this->post(route('admin.settings.ai.update'), [
            'ai_enabled' => true,
            'ai_provider' => 'unsupported',
            'api_key' => $secret,
        ])->assertSessionHasErrors('ai_provider')->assertSessionMissing('_old_input.api_key');
    }
}
