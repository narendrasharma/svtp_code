<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost/code');
        URL::forceRootUrl('http://localhost');
    }

    public function test_public_shared_navigation_has_ordered_hierarchy_and_resolves_base_paths(): void
    {
        $page = Page::factory()->create(['slug' => 'visitor-guide', 'is_active' => true]);
        $header = Menu::factory()->create(['location' => 'header']);
        $root = MenuItem::factory()->create(['menu_id' => $header->id, 'title' => 'Guide', 'type' => 'page', 'page_id' => $page->id, 'url' => null]);
        $child = MenuItem::factory()->create(['menu_id' => $header->id, 'parent_id' => $root->id, 'url' => '/contact', 'sort_order' => 1]);
        $firstChild = MenuItem::factory()->create(['menu_id' => $header->id, 'parent_id' => $root->id, 'url' => 'https://example.com/travel', 'target' => '_blank', 'sort_order' => 0]);
        $grandchild = MenuItem::factory()->create(['menu_id' => $header->id, 'parent_id' => $child->id, 'url' => 'packages']);
        $footer = Menu::factory()->create(['location' => 'footer']);
        MenuItem::factory()->create(['menu_id' => $footer->id, 'url' => '/code/privacy']);
        $this->get(route('page.show', $page->slug))->assertInertia(fn (Assert $view) => $view
            ->has('navigation.header', 1)
            ->where('navigation.header.0.id', $root->id)
            ->where('navigation.header.0.href', 'http://localhost/code/visitor-guide')
            ->where('navigation.header.0.children.0.id', $firstChild->id)
            ->where('navigation.header.0.children.0.href', 'https://example.com/travel')
            ->where('navigation.header.0.children.0.target', '_blank')
            ->where('navigation.header.0.children.1.href', 'http://localhost/code/contact')
            ->where('navigation.header.0.children.1.children.0.id', $grandchild->id)
            ->where('navigation.header.0.children.1.children.0.href', 'http://localhost/code/packages')
            ->where('navigation.footer.0.href', 'http://localhost/code/privacy')
            ->missing('navigation.header.0.page')
            ->missing('navigation.header.0.menu_id'));
    }

    public function test_missing_inactive_and_unsafe_links_and_their_descendants_are_hidden(): void
    {
        $page = Page::factory()->create(['is_active' => true]);
        $inactivePage = Page::factory()->create(['is_active' => false]);
        $menu = Menu::factory()->create(['location' => 'header']);
        $inactive = MenuItem::factory()->create(['menu_id' => $menu->id, 'is_active' => false]);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $inactive->id]);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'type' => 'page', 'page_id' => null]);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'type' => 'page', 'page_id' => $inactivePage->id]);
        foreach (['javascript:alert(1)', 'data:text/html,test', '//evil.test', "java\tscript:alert(1)", '/\\evil.test'] as $url) {
            MenuItem::factory()->create(['menu_id' => $menu->id, 'url' => $url]);
        }
        $cycle = MenuItem::factory()->create(['menu_id' => $menu->id]);
        $cycle->update(['parent_id' => $cycle->id]);
        $this->get(route('page.show', $page->slug))->assertInertia(fn (Assert $view) => $view->where('navigation', ['header' => [], 'footer' => []]));
    }

    public function test_first_usable_active_menu_wins_and_inactive_or_unassigned_menus_are_ignored(): void
    {
        $page = Page::factory()->create(['is_active' => true]);
        foreach ([['location' => 'header', 'is_active' => false], ['location' => null]] as $attributes) {
            $ignored = Menu::factory()->create($attributes);
            MenuItem::factory()->create(['menu_id' => $ignored->id]);
        }
        Menu::factory()->create(['location' => 'header', 'sort_order' => 0]);
        $selected = Menu::factory()->create(['location' => 'header', 'sort_order' => 1]);
        $link = MenuItem::factory()->create(['menu_id' => $selected->id, 'url' => 'mailto:hello@example.com']);
        $later = Menu::factory()->create(['location' => 'header', 'sort_order' => 2]);
        MenuItem::factory()->create(['menu_id' => $later->id]);
        $this->get(route('page.show', $page->slug))->assertInertia(fn (Assert $view) => $view
            ->has('navigation.header', 1)->where('navigation.header.0.id', $link->id)->where('navigation.header.0.href', 'mailto:hello@example.com')->where('navigation.footer', []));
        $selected->update(['is_active' => false]);
        $later->update(['is_active' => false]);
        $this->get(route('page.show', $page->slug))->assertInertia(fn (Assert $view) => $view->where('navigation.header', []));
    }

    public function test_fresh_install_returns_empty_menu_arrays_for_static_navigation_fallback(): void
    {
        $page = Page::factory()->create(['is_active' => true]);
        $this->get(route('page.show', $page->slug))->assertInertia(fn (Assert $view) => $view->where('navigation', ['header' => [], 'footer' => []]));
    }
}
