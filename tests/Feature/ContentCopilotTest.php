<?php

namespace Tests\Feature;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ProviderCapabilities;
use App\AI\Support\AIProviderRegistry;
use App\Models\Property;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ContentCopilotTest extends TestCase
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
        config()->set('services.ai.providers.openai.key', 'test-credential');
    }

    public function test_content_request_uses_fake_provider_and_sanitizes_html_without_saving(): void
    {
        $provider = $this->fakeProvider('<p onclick="alert(1)" style="color:red">Better copy</p><script>alert(2)</script>');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai.content'), [
                'content_type' => 'tour',
                'field' => 'overview',
                'action' => 'improve',
                'source' => '<p>Current copy</p>',
                'context' => ['title' => 'Mathura Tour'],
            ])
            ->assertOk()
            ->assertJsonPath('draft', '<p>Better copy</p>');

        $this->assertStringContainsString('untrusted data', $provider->lastRequest->systemInstructions);
        $this->assertStringContainsString('Current copy', $provider->lastRequest->userContent);
        $this->assertDatabaseCount('tour_packages', 0);
    }

    public function test_disabled_and_unconfigured_copilot_return_safe_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['content_type' => 'page', 'field' => 'content', 'action' => 'improve', 'source' => 'Current copy'];
        Setting::setValue('ai.enabled', '0');

        $this->postJson(route('admin.ai.content'), $payload)->assertStatus(503)->assertJsonPath('message', 'AI is disabled.');

        Setting::setValue('ai.enabled', '1');
        config()->set('services.ai.providers.openai.key', null);
        $this->postJson(route('admin.ai.content'), $payload)->assertStatus(503)->assertJsonPath('message', 'AI is not configured.');
    }

    public function test_vendor_cannot_use_another_vendors_tour_or_hotel_as_context(): void
    {
        $provider = $this->fakeProvider('Should not be called');
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id]);
        $other = VendorProfile::factory()->create();
        $tour = TourPackage::factory()->forVendor($other)->create();
        app(ModuleManager::class)->setEnabled('hotels', true);
        $hotel = Property::factory()->create(['vendor_profile_id' => $other->id]);

        $this->actingAs($vendor)->postJson(route('vendor.ai.content'), [
            'content_type' => 'tour', 'entity_id' => $tour->id, 'field' => 'overview',
            'action' => 'improve', 'source' => 'Tour copy',
        ])->assertNotFound();
        $this->postJson(route('vendor.ai.content'), [
            'content_type' => 'hotel', 'entity_id' => $hotel->id, 'field' => 'description',
            'action' => 'improve', 'source' => 'Hotel copy',
        ])->assertNotFound();

        $this->assertNull($provider->lastRequest);
    }

    public function test_seo_draft_is_normalized_without_exposing_credentials(): void
    {
        $provider = $this->fakeProvider("```json\n".json_encode([
            'title' => str_repeat('A', 75),
            'description' => str_repeat('B', 175),
        ])."\n```");
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = [
            'content_type' => 'page', 'field' => 'seo', 'action' => 'seo',
            'context' => ['name' => 'Vrindavan Guide'],
        ];
        $response = $this->postJson(route('admin.ai.content'), $payload)
            ->assertOk()
            ->assertJsonPath('draft.title', str_repeat('A', 70))
            ->assertJsonPath('draft.description', str_repeat('B', 170));

        $this->assertStringNotContainsString('test-credential', $response->getContent());
        $this->assertStringNotContainsString('test-credential', $provider->lastRequest->userContent);
        $this->postJson(route('admin.ai.content'), [
            ...$payload, 'context' => ['name' => 'Guide', 'hidden_record' => 'secret'],
        ])->assertUnprocessable()->assertJsonValidationErrors('context');
    }

    public function test_itinerary_draft_returns_editable_days_without_persisting(): void
    {
        $provider = $this->fakeProvider(json_encode(['days' => [
            ['day' => 1, 'title' => 'Mathura', 'points' => ['Visit the listed places']],
            ['day' => 2, 'title' => 'Vrindavan', 'points' => ['Explore the listed sites']],
        ]]));
        $tour = TourPackage::factory()->create(['day_wise_itinerary' => []]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai.content'), [
                'content_type' => 'tour', 'entity_id' => $tour->id,
                'field' => 'itinerary', 'action' => 'itinerary',
                'context' => ['title' => 'Mathura and Vrindavan', 'duration_days' => 2, 'places' => ['Mathura', 'Vrindavan']],
            ])
            ->assertOk()
            ->assertJsonPath('draft.days.1.title', 'Vrindavan');

        $this->assertSame([], $tour->refresh()->day_wise_itinerary);
        $this->assertStringContainsString('Mathura', $provider->lastRequest->userContent);

        $this->postJson(route('admin.ai.content'), [
            'content_type' => 'tour', 'field' => 'itinerary', 'action' => 'itinerary',
            'context' => ['title' => 'Long tour', 'existing_itinerary' => array_fill(0, 30, [
                'points' => array_fill(0, 10, str_repeat('A', 100)),
            ])],
        ])->assertUnprocessable()->assertJsonValidationErrors('context');
    }

    private function fakeProvider(string $text): AIProviderInterface
    {
        $provider = new class($text) implements AIProviderInterface
        {
            public ?AIRequest $lastRequest = null;

            public function __construct(private readonly string $text) {}

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

                return new AIResponse($this->text, 'openai', $request->model);
            }
        };
        app(AIProviderRegistry::class)->register($provider);

        return $provider;
    }
}
