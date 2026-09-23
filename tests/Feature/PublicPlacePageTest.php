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

class PublicPlacePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_place_slug_page_shows_details_destination_and_active_linked_tours(): void
    {
        [$place, $destination, $activeTour, $inactiveTour] = $this->placeRecords();

        $this->get(route('places.show', $place, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/Place')
                ->where('place.name', 'Prem Mandir')
                ->where('place.description', 'A marble temple illuminated after sunset.')
                ->where('place.destination.id', $destination->id)
                ->where('place.destination.slug', 'vrindavan')
                ->has('place.tour_packages', 1)
                ->where('place.tour_packages.0.id', $activeTour->id));

        $this->assertNotSame($activeTour->id, $inactiveTour->id);
    }

    public function test_unknown_place_slug_returns_not_found(): void
    {
        $this->get('/places/not-a-real-place')->assertNotFound();
    }

    public function test_place_index_excludes_inactive_places_and_detail_hides_them(): void
    {
        [$place, $destination] = $this->placeRecords();
        $hidden = Place::factory()->inactive()->create([
            'destination_id' => $destination->id,
            'name' => 'Hidden public place',
            'slug' => 'hidden-public-place',
        ]);

        $this->get(route('places', absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/Places')
                ->has('places', 1)
                ->where('places.0.id', $place->id));

        $this->get(route('places.show', $hidden, absolute: false))->assertNotFound();
    }

    /**
     * @return array{Place, Destination, TourPackage, TourPackage}
     */
    private function placeRecords(): array
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create(['city_id' => $city->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);
        $place = Place::create([
            'destination_id' => $destination->id,
            'name' => 'Prem Mandir',
            'slug' => 'prem-mandir',
            'description' => 'A marble temple illuminated after sunset.',
            'image' => '/images/prem-mandir.jpg',
            'meta_description' => 'Visit Prem Mandir in Vrindavan.',
        ]);
        $activeTour = $this->createTour($city, 'Vrindavan Darshan', 'vrindavan-darshan', true);
        $inactiveTour = $this->createTour($city, 'Hidden Tour', 'hidden-tour', false);

        $place->tourPackages()->attach([$activeTour->id, $inactiveTour->id]);

        return [$place, $destination, $activeTour, $inactiveTour];
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
