<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\State;
use App\Models\Tag;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TourPackageClassificationAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_admin_can_assign_and_replace_tour_classifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$city, $destination, $place, $tag] = $this->classificationRecords();

        $this->actingAs($admin)->post(route('admin.packages.store', absolute: false), [
            ...$this->packagePayload($city),
            'destination_ids' => [$destination->id],
            'place_ids' => [$place->id],
            'tag_ids' => [$tag->id],
        ])->assertRedirect(route('admin.packages.index', absolute: false));

        $package = TourPackage::firstOrFail();

        $this->assertDatabaseHas('destination_tour_package', ['destination_id' => $destination->id, 'tour_package_id' => $package->id]);
        $this->assertDatabaseHas('place_tour_package', ['place_id' => $place->id, 'tour_package_id' => $package->id]);
        $this->assertDatabaseHas('tag_tour_package', ['tag_id' => $tag->id, 'tour_package_id' => $package->id]);

        $this->actingAs($admin)->get(route('admin.packages.edit', $package, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Packages/Form')
                ->has('destinations', 1)
                ->has('places', 1)
                ->has('tags', 1)
                ->where('package.destinations.0.id', $destination->id)
                ->where('package.places.0.id', $place->id)
                ->where('package.tags.0.id', $tag->id));

        $this->actingAs($admin)->put(route('admin.packages.update', $package, absolute: false), [
            ...$this->packagePayload($city, ['title' => 'Updated Vrindavan Darshan']),
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect(route('admin.packages.index', absolute: false));

        $this->assertSame('Updated Vrindavan Darshan', $package->fresh()->title);
        $this->assertDatabaseMissing('destination_tour_package', ['tour_package_id' => $package->id]);
        $this->assertDatabaseMissing('place_tour_package', ['tour_package_id' => $package->id]);
        $this->assertDatabaseMissing('tag_tour_package', ['tour_package_id' => $package->id]);
    }

    public function test_assignment_ids_must_reference_existing_master_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$city] = $this->classificationRecords();

        $this->actingAs($admin)->post(route('admin.packages.store', absolute: false), [
            ...$this->packagePayload($city),
            'destination_ids' => [999],
            'place_ids' => [999],
            'tag_ids' => [999],
        ])->assertSessionHasErrors(['destination_ids.0', 'place_ids.0', 'tag_ids.0']);

        $this->assertDatabaseCount('tour_packages', 0);
    }

    public function test_admin_can_store_and_update_package_content_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$city] = $this->classificationRecords();
        $content = [
            'day_wise_itinerary' => [[
                'day' => 1,
                'title' => 'Mathura Darshan',
                'points' => ['Krishna Janmabhoomi', 'Vishram Ghat aarti'],
            ]],
            'inclusions' => ['Private AC vehicle', 'Local guide'],
            'exclusions' => ['Meals'],
            'gallery' => ['/storage/packages/mathura-1.jpg'],
        ];

        $this->actingAs($admin)->post(route('admin.packages.store', absolute: false), [
            ...$this->packagePayload($city),
            ...$content,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect(route('admin.packages.index', absolute: false));

        $package = TourPackage::firstOrFail();

        $this->assertSame($content['day_wise_itinerary'], $package->day_wise_itinerary);
        $this->assertSame($content['inclusions'], $package->inclusions);
        $this->assertSame($content['exclusions'], $package->exclusions);
        $this->assertSame($content['gallery'], $package->gallery);

        $updatedContent = [
            'day_wise_itinerary' => [[
                'day' => 1,
                'title' => 'Vrindavan Darshan',
                'points' => ['Banke Bihari Temple'],
            ]],
            'inclusions' => ['Hotel pickup'],
            'exclusions' => ['Meals', 'Entry tickets'],
            'gallery' => ['/storage/packages/vrindavan-1.jpg', '/storage/packages/vrindavan-2.jpg'],
        ];

        $this->actingAs($admin)->put(route('admin.packages.update', $package, absolute: false), [
            ...$this->packagePayload($city),
            ...$updatedContent,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect(route('admin.packages.index', absolute: false));

        $package->refresh();

        $this->assertSame($updatedContent['day_wise_itinerary'], $package->day_wise_itinerary);
        $this->assertSame($updatedContent['inclusions'], $package->inclusions);
        $this->assertSame($updatedContent['exclusions'], $package->exclusions);
        $this->assertSame($updatedContent['gallery'], $package->gallery);
    }

    /**
     * @return array{City, Destination, Place, Tag}
     */
    private function classificationRecords(): array
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create(['city_id' => $city->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);
        $place = Place::create([
            'destination_id' => $destination->id,
            'name' => 'Banke Bihari Temple',
            'slug' => 'banke-bihari-temple',
        ]);
        $tag = Tag::create(['name' => 'Family', 'slug' => 'family', 'is_active' => true]);

        return [$city, $destination, $place, $tag];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function packagePayload(City $city, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Vrindavan Darshan',
            'city_id' => $city->id,
            'duration_days' => 2,
            'duration_nights' => 1,
            'price' => 5000,
            'discounted_price' => null,
            'overview' => 'A guided Braj pilgrimage.',
            'is_featured' => true,
            'is_active' => true,
        ], $overrides);
    }
}
