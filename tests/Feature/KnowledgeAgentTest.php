<?php

namespace Tests\Feature;

use App\AI\Contracts\AIToolCallingProviderInterface;
use App\AI\Contracts\AIToolInterface;
use App\AI\DTOs\AIExecutionContext;
use App\AI\DTOs\AIMessage;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\AIToolCall;
use App\AI\DTOs\AIToolRequest;
use App\AI\DTOs\AIToolResponse;
use App\AI\DTOs\ProviderCapabilities;
use App\AI\Support\AIManager;
use App\AI\Support\AIProviderRegistry;
use App\AI\Support\AIToolAction;
use App\AI\Support\AIToolRegistry;
use App\AI\Tools\MarketplaceReadTool;
use App\Models\Destination;
use App\Models\HotelBooking;
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

class KnowledgeAgentTest extends TestCase
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

    public function test_admin_uses_read_tool_and_provider_receives_evidence(): void
    {
        TourPackage::factory()->create(['title' => 'Agra Sunset Tour', 'price' => 9999]);
        $provider = $this->fakeProvider([
            new AIToolResponse('', [new AIToolCall('call-1', 'search_tours', ['query' => 'Agra'])], 'openai', 'test-model'),
            new AIToolResponse('The Agra Sunset Tour is listed.', [], 'openai', 'test-model'),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.ai-assistant.index'))->assertOk();
        $this
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Find Agra tours'])
            ->assertOk()
            ->assertJsonPath('tools_used.0', 'search_tours')
            ->assertJsonPath('answer', 'The Agra Sunset Tour is listed.');

        $this->assertTrue($provider->requests[0]->requireTool);
        $this->assertStringContainsString('Agra Sunset Tour', $provider->requests[1]->messages[3]->content);
        $this->assertStringNotContainsString('9999', $provider->requests[1]->messages[3]->content);
    }

    public function test_staff_without_reports_permission_cannot_use_overview_tool(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $role = Role::create(['name' => 'ai-catalogue-reader', 'guard_name' => 'web']);
        $role->givePermissionTo(['ai.assistant.use', 'tours.view']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $provider = $this->fakeProvider([
            new AIToolResponse('', [new AIToolCall('call-1', 'marketplace_overview', [])], 'openai', 'test-model'),
        ]);

        $this->actingAs($staff->fresh())->postJson(route('admin.ai-assistant.message'), ['question' => 'How many bookings?'])
            ->assertForbidden()->assertJsonPath('message', 'The requested tool is not available for your permissions.');

        $this->assertNotContains('marketplace_overview', array_column($provider->requests[0]->tools, 'name'));
    }

    public function test_agent_rejects_a_registered_write_tool(): void
    {
        app(AIToolRegistry::class)->register(new class implements AIToolInterface
        {
            public function name(): string
            {
                return 'cancel_booking';
            }

            public function description(): string
            {
                return 'Cancel a booking';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function action(): AIToolAction
            {
                return AIToolAction::Write;
            }

            public function requiredPermission(): string
            {
                return 'ai.assistant.use';
            }

            public function execute(AIExecutionContext $context, array $arguments): array
            {
                throw new \RuntimeException('Write tool executed');
            }
        });
        $provider = $this->fakeProvider([
            new AIToolResponse('', [new AIToolCall('call-1', 'cancel_booking', [])], 'openai', 'test-model'),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Cancel booking 123'])
            ->assertForbidden();

        $this->assertNotContains('cancel_booking', array_column($provider->requests[0]->tools, 'name'));
    }

    public function test_tool_rejects_unknown_arguments_and_bounds_results(): void
    {
        TourPackage::factory()->count(3)->create();
        $tool = new MarketplaceReadTool('search_tours');
        $context = new AIExecutionContext(userId: 1, role: 'admin', permissions: ['tours.view']);

        try {
            $tool->execute($context, ['limit' => 26]);
            $this->fail('A limit above the maximum was accepted.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        try {
            $tool->execute($context, ['vendor_id' => 2]);
            $this->fail('An unknown model argument was accepted.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $result = $tool->execute($context, ['limit' => 2]);
        $this->assertCount(2, $result['items']);
        $this->assertTrue($result['limited']);
        $this->assertArrayNotHasKey('price', $result['items'][0]);

        app(ModuleManager::class)->setEnabled('hotels', true);
        HotelBooking::factory()->create(['guest_email' => 'private@example.com', 'total' => '9900.00']);
        $hotelBookings = (new MarketplaceReadTool('search_hotel_bookings'))->execute(
            new AIExecutionContext(userId: 1, role: 'admin', permissions: ['hotel.bookings.view']),
            ['limit' => 1],
        );
        $this->assertCount(1, $hotelBookings['items']);
        $this->assertArrayNotHasKey('guest_email', $hotelBookings['items'][0]);
        $this->assertArrayNotHasKey('total', $hotelBookings['items'][0]);
        $departures = (new MarketplaceReadTool('search_hotel_bookings'))->execute(
            new AIExecutionContext(userId: 1, role: 'admin', permissions: ['hotel.bookings.view']),
            ['date_field' => 'departure', 'from' => now()->addDays(32)->toDateString(), 'to' => now()->addDays(32)->toDateString()],
        );
        $this->assertCount(1, $departures['items']);

        $destination = Destination::factory()->create(['name' => 'Agra']);
        TourPackage::first()->destinations()->attach($destination);
        $geography = (new MarketplaceReadTool('search_geography'))->execute($context, ['type' => 'destination', 'query' => 'Agra']);
        $this->assertSame(1, $geography['items']['destinations'][0]['active_tours_count']);
    }

    public function test_openai_adapter_normalizes_tool_calls_and_tool_results(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::sequence()
            ->push(['model' => 'test-model', 'choices' => [['message' => [
                'content' => null, 'tool_calls' => [[
                    'id' => 'call-1', 'type' => 'function',
                    'function' => ['name' => 'search_tours', 'arguments' => '{"query":"Agra"}'],
                ]],
            ]]]])
            ->push(['model' => 'test-model', 'choices' => [['message' => ['content' => 'Found one tour.']]]]),
        ]);

        $messages = [new AIMessage('system', 'Read only.'), new AIMessage('user', 'Find Agra tours')];
        $tools = [['name' => 'search_tours', 'description' => 'Search tours', 'input_schema' => ['type' => 'object', 'properties' => []]]];
        $first = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, $tools, true));
        $this->assertSame('search_tours', $first->toolCalls[0]->name);
        $this->assertSame(['query' => 'Agra'], $first->toolCalls[0]->arguments);

        $messages[] = new AIMessage('assistant', '', $first->toolCalls);
        $messages[] = new AIMessage('tool', '{"items":[]}', toolCallId: 'call-1');
        $second = app(AIManager::class)->chatWithTools(new AIToolRequest($messages, $tools));
        $this->assertSame('Found one tour.', $second->text);
        Http::assertSent(fn ($request): bool => isset($request['messages'][3]) && $request['messages'][3]['role'] === 'tool'
            && $request['messages'][3]['tool_call_id'] === 'call-1');
    }

    public function test_disabled_or_unsupported_agent_fails_safely_and_customer_cannot_access_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Show bookings'])
            ->assertForbidden();

        $this->actingAs($admin);
        Setting::setValue('ai.enabled', '0');
        $this->postJson(route('admin.ai-assistant.message'), ['question' => 'Show tours'])
            ->assertStatus(503)->assertJsonPath('message', 'AI is disabled.');

        Setting::setValue('ai.enabled', '1');
        Setting::setValue('ai.provider', 'gemini');
        Setting::setValue('ai.model', 'gemini-1.5-flash');
        config()->set('services.ai.providers.gemini.key', 'test-credential');
        $this->postJson(route('admin.ai-assistant.message'), ['question' => 'Show tours'])
            ->assertStatus(503)->assertJsonPath('message', 'The selected AI provider or model does not support the assistant.');
    }

    /** @param list<AIToolResponse> $responses */
    private function fakeProvider(array $responses): AIToolCallingProviderInterface
    {
        $provider = new class($responses) implements AIToolCallingProviderInterface
        {
            /** @var list<AIToolRequest> */
            public array $requests = [];

            /** @param list<AIToolResponse> $responses */
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
                throw new \RuntimeException('Unexpected text generation');
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
