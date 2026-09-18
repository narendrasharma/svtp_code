<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\User;
use App\Services\HomepageSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function section(string $key): HomepageSection
    {
        return HomepageSection::query()->where('section_key', $key)->firstOrFail();
    }

    /** @return array<int, array{id: int, sort_order: int}> */
    protected function fullOrderPayload(): array
    {
        return HomepageSection::query()->whereIn('section_key', HomepageSectionService::defaultOrder())
            ->orderBy('id')
            ->get()
            ->map(fn (HomepageSection $section, int $index): array => ['id' => $section->id, 'sort_order' => $index])
            ->all();
    }

    public function test_admin_can_open_the_section_manager_with_seeded_sections(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.homepage-sections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/HomepageSections/Index')
                ->has('sections', count(HomepageSectionService::defaultOrder()))
                ->where('sections.0.key', 'hero')
            );
    }

    public function test_admin_can_toggle_section_visibility_without_losing_settings(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('testimonials');
        $section->update(['settings' => ['heading' => 'Guest Love', 'max_items' => 4]]);

        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('homepage_sections', [
            'id' => $section->id,
            'is_active' => false,
            'settings' => json_encode(['heading' => 'Guest Love', 'max_items' => 4]),
        ]);

        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('homepage_sections', ['id' => $section->id, 'is_active' => true]);
    }

    public function test_admin_can_persist_section_order(): void
    {
        $this->actingAsAdmin();
        $ids = HomepageSection::query()->whereIn('section_key', HomepageSectionService::defaultOrder())
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $payload = collect(array_reverse($ids))
            ->map(fn (int $id, int $position): array => ['id' => $id, 'sort_order' => $position])
            ->values()
            ->all();

        $this->put(route('admin.homepage-sections.reorder'), ['sections' => $payload])
            ->assertRedirect();

        foreach ($payload as $entry) {
            $this->assertDatabaseHas('homepage_sections', ['id' => $entry['id'], 'sort_order' => $entry['sort_order']]);
        }
        $this->assertSame(array_reverse($ids), HomepageSection::query()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_invalid_reorder_payloads_are_rejected_without_changes(): void
    {
        $this->actingAsAdmin();
        $before = HomepageSection::query()->pluck('sort_order', 'id')->all();
        $valid = $this->fullOrderPayload();

        $invalid = [
            ['sections' => array_slice($valid, 1)], // missing an id
            ['sections' => array_merge(array_slice($valid, 1), [['id' => 999999, 'sort_order' => 0]])], // foreign id
            ['sections' => array_map(fn ($entry) => ['id' => $entry['id'], 'sort_order' => 0], $valid)], // duplicate positions
            ['sections' => []], // empty set
            ['sections' => 'not-an-array'],
        ];

        foreach ($invalid as $payload) {
            $this->putJson(route('admin.homepage-sections.reorder'), $payload)->assertUnprocessable();
            $this->assertSame($before, HomepageSection::query()->pluck('sort_order', 'id')->all());
        }
    }

    public function test_section_settings_are_validated(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('featured_packages');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['heading' => str_repeat('a', 121), 'max_items' => 6, 'source' => 'featured'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.heading');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['heading' => 'Tours', 'max_items' => 13, 'source' => 'featured'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.max_items');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['heading' => 'Tours', 'max_items' => 6, 'source' => 'random'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.source');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['custom_html' => '<script>alert(1)</script>'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.custom_html');

        $this->put(route('admin.homepage-sections.update', $section), [
            'is_active' => true,
            'settings' => ['heading' => 'Handpicked Tours', 'max_items' => 3, 'source' => 'latest'],
        ])->assertRedirect();

        $section->refresh();
        $this->assertSame(['heading' => 'Handpicked Tours', 'max_items' => 3, 'source' => 'latest'], $section->settings);
    }

    public function test_save_and_reorder_carry_the_flash_contract(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('faq');

        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => false])
            ->assertRedirect();
        $this->get(route('admin.homepage-sections.index'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.message', 'Homepage section saved.'));

        $this->put(route('admin.homepage-sections.reorder'), ['sections' => $this->fullOrderPayload()])
            ->assertRedirect();
        $this->get(route('admin.homepage-sections.index'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.message', 'Section order saved.'));
    }

    public function test_section_manager_requires_an_admin(): void
    {
        $section = $this->section('faq');

        $this->get(route('admin.homepage-sections.index'))->assertRedirect(route('login'));
        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => false])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->get(route('admin.homepage-sections.index'))->assertForbidden();
        $this->put(route('admin.homepage-sections.reorder'), ['sections' => []])->assertForbidden();
    }
}
