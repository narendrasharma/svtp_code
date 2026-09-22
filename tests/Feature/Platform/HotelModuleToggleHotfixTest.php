<?php

namespace Tests\Feature\Platform;

use App\Models\Setting;
use App\Models\User;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HotelModuleToggleHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_admin_can_enable_and_disable_hotel_without_touching_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue(app(ModuleManager::class)->isAvailable('hotel'));

        $this->actingAs($admin)->patch(route('admin.modules.update', 'hotel'), ['enabled' => true])->assertRedirect();
        $this->assertTrue(app(ModuleManager::class)->isEnabled('hotels'));
        $this->assertSame('1', Setting::getValue('modules.hotels.enabled'));

        $this->actingAs($admin)->patch(route('admin.modules.update', 'hotels'), ['enabled' => false])->assertRedirect();
        $this->assertFalse(app(ModuleManager::class)->isEnabled('hotel'));
        $this->assertSame('0', Setting::getValue('modules.hotels.enabled'));
    }

    public function test_invalid_module_key_is_rejected_without_setting_a_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.modules.update', 'accommodation'), ['enabled' => true])->assertRedirect();
        $this->assertNull(Setting::getValue('modules.accommodation.enabled'));
    }

    public function test_platform_modules_page_exposes_deployment_base_path(): void
    {
        config()->set('app.url', 'https://shreevrindavantourandpackages.com/code');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('name="app-base" content="/code"', false);
    }
}
