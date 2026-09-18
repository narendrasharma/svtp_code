<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\HomepageSection;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function seedHomepageData(): void
    {
        $package = TourPackage::factory()->featured()->create(['title' => 'Vrindavan Darshan Tour']);
        $user = User::factory()->create(['name' => 'Meera Guest']);
        Review::create([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'reviewer_name' => 'Meera Guest',
            'rating' => 5,
            'comment' => 'A wonderful journey from start to finish.',
            'is_approved' => true,
        ]);
        Banner::create([
            'image_path' => 'banners/hero.jpg',
            'title' => 'Welcome to Braj',
            'subtitle' => 'Guided darshan tours',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_homepage_loads_with_testimonials_in_expected_shape(): void
    {
        $this->seedHomepageData();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('testimonials', 1)
                ->where('testimonials.0.rating', 5)
                ->where('testimonials.0.comment', 'A wonderful journey from start to finish.')
                ->where('testimonials.0.user.name', 'Meera Guest')
                ->where('testimonials.0.package.title', 'Vrindavan Darshan Tour')
                ->where('banners.0.title', 'Welcome to Braj')
            );
    }

    public function test_stale_serialized_models_in_cache_are_rebuilt_safely(): void
    {
        $this->seedHomepageData();

        // Simulates a cache entry written by an older deploy as Eloquent models.
        Cache::put('home.testimonials.9', Review::with('user:id,name', 'package:id,title')->get(), 1800);
        Cache::put('home.banners', Banner::query()->get(), 4);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials', 1)
                ->where('testimonials.0.rating', 5)
                ->has('banners', 1)
            );

        $this->assertIsArray(Cache::get('home.testimonials.9'));
        $this->assertIsArray(Cache::get('home.banners'));
    }

    public function test_incomplete_class_cache_payload_does_not_crash_the_homepage(): void
    {
        $this->seedHomepageData();

        // Faithful reproduction of the production failure: unserializing an
        // unknown class yields __PHP_Incomplete_Class.
        $missingClass = 'Removed\\Legacy\\TestimonialModel';
        $stale = unserialize(sprintf('O:%d:"%s":0:{}', strlen($missingClass), $missingClass));
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $stale);
        Cache::put('home.testimonials.9', $stale, 1800);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('testimonials', 1));

        $rebuilt = Cache::get('home.testimonials.9');
        $this->assertIsArray($rebuilt);
        $this->assertSame(5, $rebuilt[0]['rating']);
    }

    public function test_disabled_testimonials_section_skips_its_data(): void
    {
        $this->seedHomepageData();
        HomepageSection::query()->where('section_key', 'testimonials')->update(['is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('testimonials', []));
    }
}
