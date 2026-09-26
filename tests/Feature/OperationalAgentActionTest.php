<?php

namespace Tests\Feature;

use App\AI\Contracts\AIToolCallingProviderInterface;
use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\AIToolCall;
use App\AI\DTOs\AIToolRequest;
use App\AI\DTOs\AIToolResponse;
use App\AI\DTOs\ProviderCapabilities;
use App\AI\Support\AIProviderRegistry;
use App\Models\ActivityLog;
use App\Models\AiActionProposal;
use App\Models\HotelAmenity;
use App\Models\Page;
use App\Models\Place;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationalAgentActionTest extends TestCase
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
        Setting::setValue('ai.agent_actions.enabled', '1');
        Setting::setValue('modules.hotels.enabled', '1');
        config()->set('services.ai.providers.openai.key', 'test-key');
    }

    public function test_each_new_action_previews_one_record_without_mutation(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $hotel = Property::factory()->create(['status' => 'published', 'is_featured' => false]);
        $place = Place::factory()->create(['excerpt' => 'Original place excerpt']);
        $page = Page::factory()->create(['meta_description' => 'Original SEO text']);
        $cases = [
            ['set_hotel_featured', ['property_id' => $hotel->id, 'featured' => true], 'Mark this hotel property featured', false, true],
            ['update_place_excerpt', ['place_id' => $place->id, 'excerpt' => 'Fresh place summary'], 'Update this place excerpt', 'Original place excerpt', 'Fresh place summary'],
            ['update_page_meta_description', ['page_id' => $page->id, 'meta_description' => 'Fresh CMS summary'], 'Update this page meta description', 'Original SEO text', 'Fresh CMS summary'],
        ];

        foreach ($cases as [$name, $arguments, $question, $before, $proposed]) {
            $provider = $this->fakeProvider($name, $arguments);
            $this->actingAs($user)->postJson(route('admin.ai-assistant.message'), ['question' => $question])
                ->assertOk()->assertJsonPath('proposal.tool_name', $name)
                ->assertJsonPath('proposal.before', $before)->assertJsonPath('proposal.proposed', $proposed);
            $this->assertContains($name, array_column($provider->requests[0]->tools, 'name'));
        }

        $this->assertFalse($hotel->fresh()->is_featured);
        $this->assertSame('Original place excerpt', $place->fresh()->excerpt);
        $this->assertSame('Original SEO text', $page->fresh()->meta_description);
        $this->assertDatabaseCount('ai_action_proposals', 3);
    }

    public function test_hotel_confirmation_uses_focused_service_and_preserves_amenities(): void
    {
        $hotel = Property::factory()->create(['status' => 'published', 'is_featured' => false]);
        $amenity = HotelAmenity::factory()->create();
        $hotel->amenities()->attach($amenity);
        $proposal = $this->requestProposal('set_hotel_featured', ['property_id' => $hotel->id, 'featured' => true], 'Mark this hotel property featured');

        $this->postJson(route('admin.ai-assistant.actions.confirm', $proposal->id))
            ->assertOk()->assertJsonPath('result.is_featured', true);
        $this->postJson(route('admin.ai-assistant.actions.confirm', $proposal->id))->assertOk();

        $this->assertTrue($hotel->fresh()->is_featured);
        $this->assertTrue($hotel->fresh()->amenities()->whereKey($amenity->id)->exists());
        $this->assertSame(1, ActivityLog::where('event', 'ai.action.executed')->count());
    }

    public function test_staff_without_hotel_publish_permission_does_not_receive_action(): void
    {
        $hotel = Property::factory()->create(['status' => 'published', 'is_featured' => false]);
        $staff = User::factory()->create(['role' => 'admin']);
        $role = Role::create(['name' => 'hotel-editor-no-publish', 'guard_name' => 'web']);
        $role->givePermissionTo(['ai.assistant.use', 'hotel.properties.manage']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $provider = $this->fakeProvider('set_hotel_featured', ['property_id' => $hotel->id, 'featured' => true]);

        $this->actingAs($staff->fresh())->postJson(route('admin.ai-assistant.message'), [
            'question' => 'Mark this hotel property featured',
        ])->assertForbidden();

        $this->assertNotContains('set_hotel_featured', array_column($provider->requests[0]->tools, 'name'));
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_stale_place_excerpt_blocks_confirmation(): void
    {
        $place = Place::factory()->create(['excerpt' => 'Before']);
        $proposal = $this->requestProposal('update_place_excerpt', ['place_id' => $place->id, 'excerpt' => 'After'], 'Update this place excerpt');
        $place->update(['excerpt' => 'Changed by editor']);

        $this->postJson(route('admin.ai-assistant.actions.confirm', $proposal->id))->assertStatus(409);

        $this->assertSame('Changed by editor', $place->fresh()->excerpt);
        $this->assertSame('stale', $proposal->fresh()->failure_reason);
    }

    public function test_revoked_hotel_publish_permission_blocks_existing_proposal(): void
    {
        $hotel = Property::factory()->create(['status' => 'published', 'is_featured' => false]);
        $staff = User::factory()->create(['role' => 'admin']);
        $role = Role::create(['name' => 'hotel-merchandising-editor', 'guard_name' => 'web']);
        $role->givePermissionTo(['ai.assistant.use', 'hotel.properties.manage', 'hotel.properties.publish']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->fakeProvider('set_hotel_featured', ['property_id' => $hotel->id, 'featured' => true]);
        $this->actingAs($staff->fresh())->postJson(route('admin.ai-assistant.message'), [
            'question' => 'Mark this hotel property featured',
        ])->assertOk();
        $proposal = AiActionProposal::firstOrFail();

        $role->revokePermissionTo('hotel.properties.publish');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($staff->fresh())->postJson(route('admin.ai-assistant.actions.confirm', $proposal->id))
            ->assertForbidden();

        $this->assertFalse($hotel->fresh()->is_featured);
        $this->assertSame('denied', $proposal->fresh()->failure_reason);
    }

    public function test_text_validation_and_unknown_arguments_reject_proposals(): void
    {
        $page = Page::factory()->create();
        $this->fakeProvider('update_page_meta_description', [
            'page_id' => $page->id, 'meta_description' => str_repeat('x', 256),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('admin.ai-assistant.message'), ['question' => 'Update this page meta description'])
            ->assertStatus(422);

        $this->fakeProvider('update_page_meta_description', [
            'page_id' => $page->id, 'meta_description' => 'Good summary', 'user_id' => 999,
        ]);
        $this->postJson(route('admin.ai-assistant.message'), ['question' => 'Update this page meta description'])
            ->assertStatus(422);
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_disabled_and_bulk_requests_do_not_advertise_or_create_actions(): void
    {
        $hotel = Property::factory()->create(['status' => 'published', 'is_featured' => false]);
        $user = User::factory()->create(['role' => 'admin']);
        Setting::setValue('ai.agent_actions.enabled', '0');
        $provider = $this->fakeProvider('set_hotel_featured', ['property_id' => $hotel->id, 'featured' => true]);
        $this->actingAs($user)->postJson(route('admin.ai-assistant.message'), ['question' => 'Mark this hotel property featured'])
            ->assertStatus(503);
        $this->assertNotContains('set_hotel_featured', array_column($provider->requests[0]->tools, 'name'));

        Setting::setValue('ai.agent_actions.enabled', '1');
        $provider = $this->fakeProvider('set_hotel_featured', ['property_id' => $hotel->id, 'featured' => true]);
        $this->postJson(route('admin.ai-assistant.message'), ['question' => 'Feature all hotel properties'])
            ->assertForbidden();
        $this->assertNotContains('set_hotel_featured', array_column($provider->requests[0]->tools, 'name'));
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_action_history_is_audited_and_scoped_to_staff_owner(): void
    {
        $page = Page::factory()->create(['meta_description' => 'Before']);
        $proposal = $this->requestProposal('update_page_meta_description', [
            'page_id' => $page->id, 'meta_description' => 'After',
        ], 'Update this page meta description');
        $owner = auth()->user();
        $this->postJson(route('admin.ai-assistant.actions.confirm', $proposal->id))->assertOk();
        $this->assertSame('After', $page->fresh()->meta_description);
        $this->assertDatabaseHas('activity_logs', ['event' => 'ai.action.executed']);

        $staff = User::factory()->create(['role' => 'admin']);
        $role = Role::create(['name' => 'assistant-history-only', 'guard_name' => 'web']);
        $role->givePermissionTo('ai.assistant.use');
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($staff->fresh())->get(route('admin.ai-assistant.actions.index'))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Admin/AiAssistant/Actions')->has('proposals.data', 0));
        $this->actingAs($owner)->get(route('admin.ai-assistant.actions.index'))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Admin/AiAssistant/Actions')->has('proposals.data', 1));
    }

    private function requestProposal(string $name, array $arguments, string $question): AiActionProposal
    {
        $this->fakeProvider($name, $arguments);
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->postJson(route('admin.ai-assistant.message'), ['question' => $question])
            ->assertOk()->assertJsonPath('proposal.status', 'pending');

        return AiActionProposal::latest()->firstOrFail();
    }

    private function fakeProvider(string $name, array $arguments): AIToolCallingProviderInterface
    {
        $provider = new class($name, $arguments) implements AIToolCallingProviderInterface
        {
            /** @var list<AIToolRequest> */
            public array $requests = [];

            public function __construct(private string $name, private array $arguments) {}

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

                return new AIToolResponse('', [new AIToolCall('call-1', $this->name, $this->arguments)], 'openai', 'test-model');
            }
        };
        app(AIProviderRegistry::class)->register($provider);

        return $provider;
    }
}
