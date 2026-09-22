<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\State;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTourMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_admin_can_manage_destinations_with_an_optional_city(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $city = $this->createCity();

        $this->actingAs($admin)->get(route('admin.destinations.create', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Destinations/Form')
                ->has('cities', 1));

        $this->actingAs($admin)->post(route('admin.destinations.store', absolute: false), [
            'name' => 'Vrindavan',
            'slug' => 'vrindavan',
            'city_id' => $city->id,
            'is_active' => true,
            'image_upload' => UploadedFile::fake()->image('vrindavan.jpg'),
        ])->assertRedirect(route('admin.destinations.index', absolute: false));

        $destination = Destination::firstOrFail();
        $image = $destination->image;
        $this->assertStringStartsWith('/storage/destinations/', $image);
        Storage::disk('public')->assertExists('destinations/'.basename($image));

        $this->actingAs($admin)->put(route('admin.destinations.update', $destination, absolute: false), [
            'name' => 'Vrindavan Dham',
            'slug' => 'vrindavan-dham',
            'city_id' => null,
            'is_active' => true,
        ])->assertRedirect(route('admin.destinations.index', absolute: false));

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'name' => 'Vrindavan Dham',
            'city_id' => null,
            'image' => $image,
        ]);

        $this->actingAs($admin)->put(route('admin.destinations.update', $destination, absolute: false), [
            'name' => 'Vrindavan Dham',
            'slug' => 'vrindavan-dham',
            'city_id' => null,
            'is_active' => true,
            'remove_image' => true,
        ])->assertRedirect(route('admin.destinations.index', absolute: false));

        $this->assertNull($destination->refresh()->image);
        Storage::disk('public')->assertMissing('destinations/'.basename($image));

        $this->actingAs($admin)->delete(route('admin.destinations.destroy', $destination, absolute: false))->assertRedirect();
        $this->assertModelMissing($destination);
    }

    public function test_admin_can_manage_places_for_a_destination(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $destination = Destination::create(['name' => 'Vrindavan', 'slug' => 'vrindavan']);

        $this->actingAs($admin)->post(route('admin.places.store', absolute: false), [
            'name' => 'Banke Bihari Temple',
            'slug' => 'banke-bihari-temple',
            'destination_id' => $destination->id,
            'image_upload' => UploadedFile::fake()->image('temple.webp'),
        ])->assertRedirect(route('admin.places.index', absolute: false));

        $place = Place::firstOrFail();
        $image = $place->image;
        $this->assertStringStartsWith('/storage/places/', $image);
        Storage::disk('public')->assertExists('places/'.basename($image));

        $this->actingAs($admin)->put(route('admin.places.update', $place, absolute: false), [
            'name' => 'Shri Banke Bihari Temple',
            'slug' => 'shri-banke-bihari-temple',
            'destination_id' => $destination->id,
        ])->assertRedirect(route('admin.places.index', absolute: false));

        $this->assertDatabaseHas('places', [
            'id' => $place->id,
            'name' => 'Shri Banke Bihari Temple',
            'image' => $image,
        ]);

        $this->actingAs($admin)->put(route('admin.places.update', $place, absolute: false), [
            'name' => 'Shri Banke Bihari Temple',
            'slug' => 'shri-banke-bihari-temple',
            'destination_id' => $destination->id,
            'remove_image' => true,
        ])->assertRedirect(route('admin.places.index', absolute: false));

        $this->assertNull($place->refresh()->image);
        Storage::disk('public')->assertMissing('places/'.basename($image));

        $this->actingAs($admin)->delete(route('admin.places.destroy', $place, absolute: false))->assertRedirect();
        $this->assertModelMissing($place);
    }

    public function test_admin_can_manage_active_and_inactive_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.tags.store', absolute: false), [
            'name' => 'Family',
            'slug' => 'family',
            'is_active' => true,
        ])->assertRedirect(route('admin.tags.index', absolute: false));

        $tag = Tag::firstOrFail();

        $this->actingAs($admin)->put(route('admin.tags.update', $tag, absolute: false), [
            'name' => 'Family Friendly',
            'slug' => 'family-friendly',
            'is_active' => false,
        ])->assertRedirect(route('admin.tags.index', absolute: false));

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'slug' => 'family-friendly',
            'is_active' => false,
        ]);

        $this->actingAs($admin)->delete(route('admin.tags.destroy', $tag, absolute: false))->assertRedirect();
        $this->assertModelMissing($tag);
    }

    private function createCity(): City
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);

        return City::create([
            'state_id' => $state->id,
            'name' => 'Mathura',
            'slug' => 'mathura',
        ]);
    }
}
