<?php

namespace Tests\Feature\Platform;

use App\Models\Setting;
use App\Models\User;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ModuleManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function disableTours(): void
    {
        Setting::setValue('modules.tours.enabled', '0');
    }

    public function test_modules_enabled_by_default(): void
    {
        $manager = app(ModuleManager::class);

        $this->assertTrue($manager->isEnabled('tours'));
        $this->assertTrue($manager->isEnabled('taxi'));
        $this->assertFalse($manager->isEnabled('hotels'));
        $this->assertContains('tours', $manager->active());
        $this->assertContains('taxi', $manager->active());
    }

    public function test_future_modules_cannot_be_enabled(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ModuleManager::class)->setEnabled('hotels', true);
    }

    public function test_unknown_module_is_never_enabled(): void
    {
        $this->assertFalse(app(ModuleManager::class)->isEnabled('crm'));
    }

    public function test_modules_page_lists_future_status(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.modules.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Modules/Index')
            ->has('modules', 3)
            ->where('modules.0.key', 'tours')
            ->where('modules.0.available', true)
            ->where('modules.1.key', 'taxi')
            ->where('modules.1.available', true)
            ->where('modules.2.key', 'hotels')
            ->where('modules.2.available', false));
    }

    public function test_taxi_routes_registered_while_hotels_still_future(): void
    {
        $this->assertTrue(Route::has('admin.taxi.dashboard'));
        $this->assertFalse(Route::has('admin.hotels.index'));
    }

    public function test_disabled_tours_hides_admin_navigation_section(): void
    {
        $admin = $this->admin();
        $this->disableTours();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $navigation = $response->viewData('page')['props']['adminNavigation'];
        $keys = collect($navigation)->pluck('key')->all();

        $this->assertNotContains('tours', $keys);
        $this->assertContains('dashboard', $keys);
    }

    public function test_disabled_tours_hides_global_search_results(): void
    {
        $admin = $this->admin();
        $this->disableTours();

        $response = $this->actingAs($admin)->getJson(route('admin.search', ['q' => 'mathura']));
        $response->assertOk();

        $keys = collect($response->json('groups'))->pluck('key')->all();
        $this->assertNotContains('tours', $keys);
    }

    public function test_module_middleware_blocks_disabled_module_routes(): void
    {
        $admin = $this->admin();
        $this->disableTours();

        $this->actingAs($admin)->get(route('admin.packages.index'))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.destinations.index'))->assertNotFound();
        $this->get(route('packages.index'))->assertNotFound();
    }

    public function test_shared_platform_pages_remain_available_when_tours_disabled(): void
    {
        $admin = $this->admin();
        $this->disableTours();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk();
    }

    public function test_modules_toggle_requires_permission(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole('content-manager');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staff)->get(route('admin.modules.index'))->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.modules.update', 'tours'), ['enabled' => false])->assertForbidden();
    }

    public function test_super_admin_can_toggle_tours_back_on(): void
    {
        $admin = $this->admin();
        $this->disableTours();
        $this->assertTrue(app(ModuleManager::class)->isDisabled('tours'));

        $this->actingAs($admin)
            ->patch(route('admin.modules.update', 'tours'), ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue(app(ModuleManager::class)->isEnabled('tours'));
    }
}
