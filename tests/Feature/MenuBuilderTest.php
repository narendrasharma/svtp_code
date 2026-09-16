<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_admin_can_create_assign_and_open_a_menu_builder(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.menus.store'), ['name' => 'Main', 'slug' => 'main', 'location' => 'header', 'is_active' => true, 'sort_order' => 0])->assertRedirect();
        $menu = Menu::sole();
        $this->assertSame('header', $menu->location);
        $this->get(route('admin.menus.edit', $menu))->assertRedirect(route('admin.menus.items.index', $menu));
        $this->get(route('admin.menus.items.index', $menu))->assertInertia(fn (Assert $page) => $page->component('Admin/Menus/Edit')->where('menu.id', $menu->id)->has('pages')->has('menus'));
        $this->put(route('admin.menus.update', $menu), ['name' => 'Footer', 'slug' => 'main', 'location' => 'footer', 'is_active' => false, 'sort_order' => 2])->assertRedirect(route('admin.menus.items.index', $menu));
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'location' => 'footer', 'is_active' => false]);
    }

    public function test_admin_can_add_multiple_pages_and_a_custom_link_then_edit_and_remove_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $menu = Menu::factory()->create();
        $pages = Page::factory()->count(2)->create(['is_active' => true]);
        $this->post(route('admin.menus.items.pages', $menu), ['page_ids' => $pages->modelKeys()])->assertRedirect();
        foreach ($pages as $index => $page) {
            $this->assertDatabaseHas('menu_items', ['menu_id' => $menu->id, 'page_id' => $page->id, 'title' => $page->title, 'sort_order' => $index, 'type' => 'page']);
        }
        $payload = ['title' => 'Contact', 'type' => 'custom', 'url' => '/contact', 'page_id' => null, 'parent_id' => null, 'sort_order' => 0, 'is_active' => true, 'target' => '_self'];
        $this->post(route('admin.menus.items.store', $menu), $payload)->assertRedirect();
        $item = $menu->items()->where('type', 'custom')->sole();
        $this->assertSame(2, $item->sort_order);
        $this->put(route('admin.menus.items.update', [$menu, $item]), array_replace($payload, ['title' => 'Get in touch', 'target' => '_blank', 'is_active' => false]))->assertRedirect();
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'title' => 'Get in touch', 'target' => '_blank', 'is_active' => false]);
        $child = MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $item->id]);
        $this->delete(route('admin.menus.items.destroy', [$menu, $item]))->assertRedirect();
        $this->assertModelMissing($item);
        $this->assertModelMissing($child);
    }

    public function test_reorder_persists_nested_items_and_can_move_them_back_to_root(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $menu = Menu::factory()->create();
        [$first, $second, $third] = MenuItem::factory()->count(3)->create(['menu_id' => $menu->id]);
        $url = route('admin.menus.items.reorder', $menu);
        $this->put($url, ['items' => [
            ['id' => $first->id, 'parent_id' => $second->id, 'sort_order' => 0],
            ['id' => $second->id, 'parent_id' => null, 'sort_order' => 0],
            ['id' => $third->id, 'parent_id' => $first->id, 'sort_order' => 0],
        ]])->assertRedirect();
        $this->assertDatabaseHas('menu_items', ['id' => $third->id, 'parent_id' => $first->id]);
        $this->assertDatabaseHas('menu_items', ['id' => $first->id, 'parent_id' => $second->id]);
        $this->put($url, ['items' => [
            ['id' => $first->id, 'parent_id' => null, 'sort_order' => 1],
            ['id' => $second->id, 'parent_id' => null, 'sort_order' => 2],
            ['id' => $third->id, 'parent_id' => null, 'sort_order' => 0],
        ]])->assertRedirect();
        $this->assertSame([$third->id, $first->id, $second->id], $menu->items()->whereNull('parent_id')->pluck('id')->all());
    }

    public function test_invalid_hierarchies_are_rejected_without_partial_updates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $menu = Menu::factory()->create();
        $first = MenuItem::factory()->create(['menu_id' => $menu->id]);
        $second = MenuItem::factory()->create(['menu_id' => $menu->id, 'sort_order' => 1]);
        $foreign = MenuItem::factory()->create();
        $valid = [['id' => $first->id, 'parent_id' => null, 'sort_order' => 0], ['id' => $second->id, 'parent_id' => null, 'sort_order' => 1]];
        $invalid = [
            [],
            [$valid[0]],
            [$valid[0], $valid[0]],
            [$valid[0], ['id' => $foreign->id, 'parent_id' => null, 'sort_order' => 1]],
            [$valid[0], array_replace($valid[1], ['parent_id' => $foreign->id])],
            [array_replace($valid[0], ['parent_id' => $first->id]), $valid[1]],
            [array_replace($valid[0], ['parent_id' => $second->id]), array_replace($valid[1], ['parent_id' => $first->id])],
            [$valid[0], array_replace($valid[1], ['sort_order' => 0])],
            [$valid[0], array_replace($valid[1], ['sort_order' => -1])],
            [$valid[0], array_replace($valid[1], ['menu_id' => $foreign->menu_id])],
        ];
        foreach ($invalid as $items) {
            $this->putJson(route('admin.menus.items.reorder', $menu), ['items' => $items])->assertUnprocessable();
            $this->assertDatabaseHas('menu_items', ['id' => $first->id, 'parent_id' => null, 'sort_order' => 0]);
            $this->assertDatabaseHas('menu_items', ['id' => $second->id, 'parent_id' => null, 'sort_order' => 1]);
        }
    }

    public function test_cross_menu_crud_and_circular_parent_edits_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $menu = Menu::factory()->create();
        $item = MenuItem::factory()->create();
        $payload = $item->only(['title', 'type', 'url', 'page_id', 'parent_id', 'sort_order', 'is_active', 'target']);
        $this->get(route('admin.menus.items.edit', [$menu, $item]))->assertNotFound();
        $this->put(route('admin.menus.items.update', [$menu, $item]), $payload)->assertNotFound();
        $this->delete(route('admin.menus.items.destroy', [$menu, $item]))->assertNotFound();
        $child = MenuItem::factory()->create(['menu_id' => $item->menu_id, 'parent_id' => $item->id]);
        $this->put(route('admin.menus.items.update', [$item->menu_id, $item]), array_replace($payload, ['parent_id' => $child->id]))->assertSessionHasErrors('parent_id');
        $this->assertModelExists($item);
    }

    public function test_inactive_pages_and_unsafe_links_cannot_be_added(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $menu = Menu::factory()->create();
        $page = Page::factory()->create(['is_active' => false]);
        $this->post(route('admin.menus.items.pages', $menu), ['page_ids' => [$page->id]])->assertSessionHasErrors('page_ids.0');
        $payload = ['title' => 'Unsafe', 'type' => 'custom', 'url' => 'javascript:alert(1)', 'parent_id' => null, 'sort_order' => 0, 'is_active' => true, 'target' => '_self'];
        $this->post(route('admin.menus.items.store', $menu), $payload)->assertSessionHasErrors('url');
        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_menu_builder_requires_an_admin(): void
    {
        $menu = Menu::factory()->create();
        $this->get(route('admin.menus.items.index', $menu))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->get(route('admin.menus.items.index', $menu))->assertForbidden();
        $this->put(route('admin.menus.items.reorder', $menu), ['items' => []])->assertForbidden();
        $this->post(route('admin.menus.items.pages', $menu), ['page_ids' => []])->assertForbidden();
    }
}
