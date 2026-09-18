<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\Review;
use App\Models\TourPackage;
use App\Services\HomepageSectionService;
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

    /** @return array<int, string> */
    protected function homepageKeys(): array
    {
        $response = $this->get(route('home'))->assertOk();

        return collect($response->viewData('page')['props']['homepageSections'] ?? [])
            ->pluck('key')
            ->all();
    }

    public function test_homepage_renders_active_sections_in_configured_order(): void
    {
        $this->assertSame(HomepageSectionService::defaultOrder(), $this->homepageKeys());

        $hero = HomepageSection::query()->where('section_key', 'hero')->firstOrFail();
        $faq = HomepageSection::query()->where('section_key', 'faq')->firstOrFail();
        $hero->update(['sort_order' => 99]);
        $faq->update(['sort_order' => -1]);

        $keys = $this->homepageKeys();
        $this->assertSame('faq', $keys[0]);
        $this->assertSame('hero', $keys[count($keys) - 1]);
    }

    public function test_inactive_sections_are_excluded_and_skip_their_data(): void
    {
        $package = TourPackage::factory()->featured()->create();
        Review::create([
            'package_id' => $package->id,
            'reviewer_name' => 'Happy Guest',
            'rating' => 5,
            'comment' => 'A wonderful journey from start to finish.',
            'is_approved' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homepageSections.5.key', 'featured_packages')
                ->has('featured', 1)
                ->has('testimonials', 1)
            );

        HomepageSection::query()->whereIn('section_key', ['featured_packages', 'testimonials'])->update(['is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('featured', [])
                ->where('testimonials', [])
                ->where('banners', [])
                ->where('destinations', [])
            );

        $keys = $this->homepageKeys();
        $this->assertNotContains('featured_packages', $keys);
        $this->assertNotContains('testimonials', $keys);
        $this->assertContains('hero', $keys);
    }

    public function test_unknown_section_keys_are_ignored(): void
    {
        HomepageSection::create([
            'section_key' => 'legacy_banner_wall',
            'is_active' => true,
            'sort_order' => -5,
            'settings' => null,
        ]);

        $this->get(route('home'))->assertOk();
        $this->assertNotContains('legacy_banner_wall', $this->homepageKeys());
    }

    public function test_missing_configuration_falls_back_to_defaults(): void
    {
        HomepageSection::query()->delete();

        $this->get(route('home'))->assertOk();
        $this->assertSame(HomepageSectionService::defaultOrder(), $this->homepageKeys());
    }

    public function test_section_settings_control_limits_and_headings(): void
    {
        TourPackage::factory()->featured()->count(3)->create();
        HomepageSection::query()->where('section_key', 'featured_packages')->update([
            'settings' => ['heading' => 'Handpicked Tours', 'max_items' => 2, 'source' => 'featured'],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('featured', 2)
                ->where('homepageSections.5.key', 'featured_packages')
                ->where('homepageSections.5.settings.heading', 'Handpicked Tours')
                ->where('homepageSections.5.settings.max_items', 2)
            );
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
