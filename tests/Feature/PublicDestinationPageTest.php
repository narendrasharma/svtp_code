<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\State;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicDestinationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_destination_index_uses_destination_records(): void
    {
        [$destination] = $this->destinationRecords();

        $this->get(route('destinations', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/Destinations')
                ->has('destinations', 1)
                ->where('destinations.0.id', $destination->id)
                ->where('destinations.0.slug', 'vrindavan')
                ->where('destinations.0.places_count', 1)
                ->where('destinations.0.tour_packages_count', 1));
    }

    public function test_destination_slug_page_shows_places_and_only_active_linked_tours(): void
    {
        [$destination, $place, $activeTour, $inactiveTour] = $this->destinationRecords();

        $this->get(route('destinations.show', $destination, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/Destination')
                ->where('destination.name', 'Vrindavan')
                ->where('destination.description', 'The sacred land of Radha and Krishna.')
                ->has('destination.places', 1)
                ->where('destination.places.0.id', $place->id)
                ->has('destination.tour_packages', 1)
                ->where('destination.tour_packages.0.id', $activeTour->id));

        $this->assertNotSame($activeTour->id, $inactiveTour->id);
    }

    public function test_unknown_destination_slug_returns_not_found(): void
    {
        $this->get('/destinations/not-a-real-destination')->assertNotFound();
    }

    public function test_city_landing_reuses_public_geography_contract(): void
    {
        [$destination] = $this->destinationRecords();

        $this->get(route('cities.show', $destination->city, absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/City')
                ->where('city.kind', 'city')
                ->where('city.name', 'Mathura')
                ->has('city.destinations', 1)
                ->where('city.destinations.0.id', $destination->id));
    }

    /**
     * @return array{Destination, Place, TourPackage, TourPackage}
     */
    private function destinationRecords(): array
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create([
            'city_id' => $city->id,
            'name' => 'Vrindavan',
            'slug' => 'vrindavan',
            'description' => 'The sacred land of Radha and Krishna.',
            'image' => '/images/vrindavan.jpg',
            'meta_description' => 'Explore Vrindavan tours and temples.',
        ]);
        $place = Place::create([
            'destination_id' => $destination->id,
            'name' => 'Banke Bihari Temple',
            'slug' => 'banke-bihari-temple',
        ]);
        $activeTour = $this->createTour($city, 'Vrindavan Darshan', 'vrindavan-darshan', true);
        $inactiveTour = $this->createTour($city, 'Hidden Tour', 'hidden-tour', false);

        $destination->tourPackages()->attach([$activeTour->id, $inactiveTour->id]);

        return [$destination, $place, $activeTour, $inactiveTour];
    }

    private function createTour(City $city, string $title, string $slug, bool $isActive): TourPackage
    {
        return TourPackage::create([
            'title' => $title,
            'slug' => $slug,
            'city_id' => $city->id,
            'duration_days' => 1,
            'duration_nights' => 0,
            'price' => 2500,
            'is_active' => $isActive,
        ]);
    }
}
