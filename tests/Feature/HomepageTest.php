<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\TourPackage;
use App\Services\HomepageSectionService;
use App\Services\HomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    /** @return array<int, array<string, mixed>> */
    protected function homepageSections(): array
    {
        $response = $this->get(route('home'))->assertOk();

        return $response->viewData('page')['props']['homepage']['sections'] ?? [];
    }

    /** @return array<int, string> */
    protected function homepageTypes(): array
    {
        return array_column($this->homepageSections(), 'type');
    }

    public function test_homepage_renders_active_sections_in_configured_order(): void
    {
        $this->assertSame(
            array_column(app(HomepageService::class)->compose()['sections'], 'type'),
            $this->homepageTypes()
        );

        $hero = HomepageSection::query()->where('section_type', 'hero_search')->firstOrFail();
        $places = HomepageSection::query()->where('section_type', 'featured_places')->firstOrFail();
        $hero->update(['sort_order' => 99]);
        $places->update(['sort_order' => -1]);

        $types = $this->homepageTypes();
        $this->assertSame('featured_places', $types[0]);
        $this->assertSame('hero_search', $types[count($types) - 1]);
    }

    public function test_inactive_sections_are_excluded_and_skip_their_data(): void
    {
        TourPackage::factory()->featured()->create();
        HomepageSection::query()->where('section_type', 'featured_tours')->update(['is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('homepageSections')
                ->where('homepage.sections', fn ($sections): bool => ! $sections->contains('type', 'featured_tours'))
            );
    }

    public function test_unknown_section_keys_are_ignored(): void
    {
        HomepageSection::create([
            'section_key' => 'legacy_banner_wall',
            'section_type' => 'legacy_banner_wall',
            'is_active' => true,
            'sort_order' => -5,
            'settings' => null,
        ]);

        $this->get(route('home'))->assertOk();
        $this->assertNotContains('legacy_banner_wall', $this->homepageTypes());
    }

    public function test_missing_configuration_returns_no_merchandising_sections(): void
    {
        HomepageSection::query()->whereNotNull('section_type')->delete();

        $this->assertSame([], $this->homepageSections());
    }

    public function test_section_settings_control_limits_and_headings(): void
    {
        TourPackage::factory()->featured()->count(3)->create();
        HomepageSection::query()->where('section_type', 'featured_tours')->update([
            'settings' => ['title' => 'Handpicked Tours'],
            'item_limit' => 2,
        ]);

        $tours = collect($this->homepageSections())->firstWhere('type', 'featured_tours');

        $this->assertSame('Handpicked Tours', $tours['title']);
        $this->assertCount(2, $tours['items']);
    }

    public function test_settings_cast_to_array_and_merge_with_defaults(): void
    {
        $section = HomepageSection::query()->where('section_key', 'testimonials')->firstOrFail();
        $section->update(['settings' => ['max_items' => 4]]);

        $this->assertIsArray($section->refresh()->settings);
        $this->assertSame(
            ['heading' => 'What Our Guests Say', 'max_items' => 4],
            HomepageSectionService::mergeSettings('testimonials', $section->settings)
        );
    }
}
