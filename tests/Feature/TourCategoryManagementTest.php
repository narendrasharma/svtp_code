<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\State;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TourCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_admin_can_manage_tour_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.tour-categories.store', absolute: false), [
            'name' => 'Temple Trails',
            'slug' => 'temple-trails',
            'icon' => 'bi-bank2',
            'description' => 'Temple visits across Braj.',
            'is_active' => true,
            'sort_order' => 2,
        ])->assertRedirect(route('admin.tour-categories.index', absolute: false));

        $category = TourCategory::firstOrFail();

        $this->assertDatabaseHas('tour_categories', [
            'id' => $category->id,
            'slug' => 'temple-trails',
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)->put(route('admin.tour-categories.update', $category, absolute: false), [
            'name' => 'Temple Journeys',
            'slug' => 'temple-journeys',
            'icon' => 'bi-map',
            'description' => null,
            'is_active' => false,
            'sort_order' => 1,
        ])->assertRedirect(route('admin.tour-categories.index', absolute: false));

        $this->assertDatabaseHas('tour_categories', [
            'id' => $category->id,
            'slug' => 'temple-journeys',
            'is_active' => false,
        ]);
    }

    public function test_package_category_filter_and_gallery_uploads_use_database_and_public_storage(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $city = $this->city();
        $templeCategory = TourCategory::factory()->create(['name' => 'Temple Trails', 'slug' => 'temple-trails']);
        $festivalCategory = TourCategory::factory()->create(['name' => 'Festival Tours', 'slug' => 'festival-tours']);

        $this->actingAs($admin)->post(route('admin.packages.store', absolute: false), [
            ...$this->packagePayload($city),
            'category_id' => $templeCategory->id,
            'gallery' => ['https://example.test/existing-image.jpg'],
            'gallery_uploads' => [UploadedFile::fake()->image('temple.jpg', 1600, 900)],
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect(route('admin.packages.index', absolute: false));

        $templePackage = TourPackage::firstOrFail();
        $this->assertSame($templeCategory->id, $templePackage->category_id);
        $this->assertSame('https://example.test/existing-image.jpg', $templePackage->gallery[0]);
        $this->assertStringStartsWith('/storage/packages/gallery/', $templePackage->gallery[1]);
        Storage::disk('public')->assertExists('packages/gallery/'.basename($templePackage->gallery[1]));

        $this->actingAs($admin)->put(route('admin.packages.update', $templePackage, absolute: false), [
            ...$this->packagePayload($city),
            'category_id' => $templeCategory->id,
            'gallery' => ['https://example.test/updated-image.jpg'],
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect(route('admin.packages.index', absolute: false));

        $templePackage->refresh();
        $this->assertSame(['https://example.test/updated-image.jpg'], $templePackage->gallery);

        TourPackage::factory()->create([
            'city_id' => $city->id,
            'title' => 'Festival Darshan',
            'slug' => 'festival-darshan',
            'category_id' => $festivalCategory->id,
            'is_active' => true,
        ]);

        $this->get(route('packages.index', ['category' => $templeCategory->slug], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Index')
                ->has('categories', 2)
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $templePackage->id)
                ->where('filters.category', $templeCategory->slug));
    }

    private function city(): City
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);

        return City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
    }

    /**
     * @return array<string, mixed>
     */
    private function packagePayload(City $city): array
    {
        return [
            'title' => 'Temple Darshan',
            'city_id' => $city->id,
            'duration_days' => 2,
            'duration_nights' => 1,
            'price' => 5000,
            'discounted_price' => null,
            'overview' => 'A guided pilgrimage.',
            'is_featured' => false,
            'is_active' => true,
        ];
    }
}
