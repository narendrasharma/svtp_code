<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminFlashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_flash_message_and_typed_keys_are_shared_with_admin_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->withSession(['flash' => 'Menu updated.', 'success' => 'All good.', 'warning' => 'Heads up.', 'info' => 'For your information.'])
            ->get(route('admin.menus.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.message', 'Menu updated.')
                ->where('flash.success', 'All good.')
                ->where('flash.warning', 'Heads up.')
                ->where('flash.info', 'For your information.')
                ->where('flash.error', null)
            );
    }

    public function test_menu_update_redirect_carries_the_legacy_flash_message(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $menu = Menu::factory()->create();

        $this->put(route('admin.menus.update', $menu), [
            'name' => 'Footer',
            'slug' => 'updated-menu',
            'location' => 'footer',
            'is_active' => false,
            'sort_order' => 2,
        ])->assertRedirect(route('admin.menus.items.index', $menu));

        $this->get(route('admin.menus.items.index', $menu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('flash.message', 'Menu updated.'));
    }
}
