<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost/code');
        URL::forceRootUrl('http://localhost');
    }

    public function test_active_about_content_is_localized_into_a_safe_public_dto(): void
    {
        Page::factory()->create([
            'slug' => 'about',
            'title' => 'Marketplace story',
            'content' => '<p>Published content</p><script>alert("unsafe")</script>',
            'meta_title' => 'Marketplace story',
            'meta_description' => 'A public platform description.',
            'is_active' => true,
        ]);

        $this->get(route('about'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/About')
                ->where('page.title', 'Marketplace story')
                ->where('page.content_available', true)
                ->where('page.content', '<p>Published content</p>alert("unsafe")')
                ->where('localizedSeo.canonical', route('about', absolute: true)));
    }

    public function test_inactive_legal_content_is_not_exposed_and_remains_noindex(): void
    {
        Page::factory()->create([
            'slug' => 'privacy',
            'is_active' => false,
            'content' => '<p>Private draft</p>',
        ]);

        $this->get(route('privacy'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/Privacy')
                ->where('page.content_available', false)
                ->where('noindex', true)
                ->where('page.content', null));
    }

    public function test_contact_enquiry_contract_accepts_a_quick_public_enquiry(): void
    {
        $this->post(route('enquiries.store'), [
            'enquiry_type' => 'quick',
            'full_name' => 'Public Traveller',
            'phone' => '+91 98765 43210',
        ])->assertRedirect();

        $this->assertDatabaseHas('enquiries', [
            'enquiry_type' => 'quick',
            'full_name' => 'Public Traveller',
        ]);
    }
}
