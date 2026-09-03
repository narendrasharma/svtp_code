<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TourPackage;
use App\Models\User;
use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_realistic_connected_catalog(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->assertSame(18, TourPackage::whereIn('slug', CatalogDemoSeeder::TOUR_SLUGS)->count());
        $this->assertSame(6, Destination::whereIn('slug', ['vrindavan', 'mathura', 'govardhan', 'barsana', 'agra', 'delhi'])->count());
        $this->assertSame(19, Place::count());
        $this->assertSame(8, Tag::whereIn('slug', ['family', 'senior-friendly', 'pilgrimage', 'same-day', 'weekend', 'heritage', 'festival', 'private-tour'])->count());

        $tour = TourPackage::where('slug', 'agra-and-vrindavan-weekend-tour')->firstOrFail();
        $this->assertEqualsCanonicalizing(['agra', 'vrindavan'], $tour->destinations()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['agra-fort', 'banke-bihari-temple', 'prem-mandir', 'taj-mahal'], $tour->places()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['heritage', 'pilgrimage', 'weekend'], $tour->tags()->pluck('slug')->all());
    }

    public function test_reset_command_preserves_users_manual_catalog_and_demo_tour_ids(): void
    {
        $this->seed(CatalogDemoSeeder::class);
        $user = User::factory()->create();
        $manualTour = TourPackage::factory()->create(['slug' => 'owner-created-custom-tour']);
        $manualDestination = Destination::factory()->create(['slug' => 'owner-created-destination']);
        $demoTour = TourPackage::where('slug', '1-night-2-days-tour')->firstOrFail();
        $demoTour->update(['title' => 'Changed demo title']);

        $this->artisan('catalog:reset-demo')->assertSuccessful();

        $this->assertModelExists($user);
        $this->assertModelExists($manualTour);
        $this->assertModelExists($manualDestination);
        $this->assertSame($demoTour->id, TourPackage::where('slug', '1-night-2-days-tour')->value('id'));
        $this->assertSame('1 Night / 2 Days Tour', TourPackage::where('slug', '1-night-2-days-tour')->value('title'));
    }
}
