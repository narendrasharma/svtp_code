<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminPagePermalinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_reserved_application_slug_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.pages.store', absolute: false), [
            'title' => 'Admin Landing',
            'slug' => 'admin',
            'template' => 'default',
            'content' => '<p>Reserved</p>',
            'is_active' => true,
            'sort_order' => 0,
        ])->assertSessionHasErrors(['slug']);

        $this->assertDatabaseMissing('pages', ['slug' => 'admin']);
    }

    public function test_page_slug_is_normalized_and_stored_as_the_public_permalink(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.pages.store', absolute: false), [
            'title' => 'About Our Marketplace',
            'slug' => 'About Our Marketplace',
            'template' => 'default',
            'content' => '<p>Published content</p>',
            'is_active' => true,
            'sort_order' => 0,
        ])->assertRedirect(route('admin.pages.index', absolute: false));

        $this->assertDatabaseHas('pages', [
            'title' => 'About Our Marketplace',
            'slug' => 'about-our-marketplace',
        ]);

        $this->get(route('page.show', ['slug' => 'about-our-marketplace'], absolute: false))
            ->assertOk();

        $legacy = Page::factory()->create(['slug' => 'legacy_page']);
        $this->actingAs($admin)->put(route('admin.pages.update', $legacy, absolute: false), [
            'title' => $legacy->title,
            'slug' => $legacy->slug,
            'template' => 'default',
            'content' => $legacy->content,
            'is_active' => true,
            'sort_order' => $legacy->sort_order,
        ])->assertRedirect(route('admin.pages.index', absolute: false));

        $this->assertDatabaseHas('pages', ['id' => $legacy->id, 'slug' => 'legacy_page']);
    }
}
