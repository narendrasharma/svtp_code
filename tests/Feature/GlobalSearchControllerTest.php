<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\State;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GlobalSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_search_returns_grouped_matches_across_public_content(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create([
            'city_id' => $city->id,
            'name' => 'Vrindavan',
            'slug' => 'vrindavan',
            'description' => 'The sacred home of Radha and Krishna.',
            'image' => '/images/vrindavan.jpg',
        ]);
        $place = Place::create([
            'destination_id' => $destination->id,
            'name' => 'Prem Mandir',
            'slug' => 'prem-mandir',
            'description' => 'A marble temple illuminated after sunset.',
            'image' => '/images/prem-mandir.jpg',
        ]);
        $tour = $this->createTour($city, 'Braj Darshan', 'braj-darshan', true);
        $tour->update([
            'overview' => 'A guided journey through the sacred towns of Braj.',
            'cover_image' => '/images/braj-tour.jpg',
        ]);
        $tour->destinations()->attach($destination);

        $response = $this->getJson('/search?q=Vrindavan');

        $response->assertOk()->assertJson([
            'tours' => [[
                'id' => $tour->id,
                'title' => 'Braj Darshan',
                'type' => 'Tour',
                'context' => 'A guided journey through the sacred towns of Braj.',
                'image' => '/images/braj-tour.jpg',
                'url' => '/packages/braj-darshan',
            ]],
            'destinations' => [[
                'id' => $destination->id,
                'title' => 'Vrindavan',
                'type' => 'Destination',
                'context' => 'The sacred home of Radha and Krishna.',
                'image' => '/images/vrindavan.jpg',
                'url' => '/destinations/vrindavan',
            ]],
            'places' => [[
                'id' => $place->id,
                'title' => 'Prem Mandir',
                'type' => 'Place',
                'context' => 'A marble temple illuminated after sunset.',
                'image' => '/images/prem-mandir.jpg',
                'url' => '/places/prem-mandir',
            ]],
        ]);
    }

    public function test_search_matches_tours_through_places_and_hides_inactive_tours(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Agra', 'slug' => 'agra']);
        $destination = Destination::create(['city_id' => $city->id, 'name' => 'Agra', 'slug' => 'agra']);
        $place = Place::create(['destination_id' => $destination->id, 'name' => 'Taj Mahal', 'slug' => 'taj-mahal']);
        $activeTour = $this->createTour($city, 'Agra Day Trip', 'agra-day-trip', true);
        $inactiveTour = $this->createTour($city, 'Hidden Agra Trip', 'hidden-agra-trip', false);
        $place->tourPackages()->attach([$activeTour->id, $inactiveTour->id]);

        $response = $this->getJson('/search?q=Taj%20Mahal');

        $response->assertOk()
            ->assertJsonCount(1, 'tours')
            ->assertJsonPath('tours.0.id', $activeTour->id)
            ->assertJsonCount(1, 'places');
    }

    public function test_search_matches_description_keywords(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create([
            'city_id' => $city->id,
            'name' => 'Braj',
            'slug' => 'braj',
            'description' => 'Experience devotional parikrama routes.',
        ]);
        Place::create([
            'destination_id' => $destination->id,
            'name' => 'Sacred Grove',
            'slug' => 'sacred-grove',
            'description' => 'A peaceful devotional landmark.',
        ]);
        $tour = $this->createTour($city, 'Braj Journey', 'braj-journey', true);
        $tour->update(['overview' => 'A devotional pilgrimage with guided parikrama.']);

        $response = $this->getJson('/search?q=devotional');

        $response->assertOk()
            ->assertJsonPath('tours.0.id', $tour->id)
            ->assertJsonPath('destinations.0.id', $destination->id)
            ->assertJsonPath('places.0.title', 'Sacred Grove');
    }

    public function test_search_returns_empty_groups_for_a_short_query(): void
    {
        $this->getJson('/search?q=V')
            ->assertOk()
            ->assertExactJson(['tours' => [], 'destinations' => [], 'places' => []]);
    }

    public function test_search_treats_query_text_as_data(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $this->createTour($city, 'Vrindavan Tour', 'vrindavan-tour', true);

        $this->getJson('/search?q=%27%20OR%201%3D1%20--')
            ->assertOk()
            ->assertExactJson(['tours' => [], 'destinations' => [], 'places' => []]);
    }

    public function test_search_returns_empty_groups_for_a_non_string_query(): void
    {
        $this->getJson('/search?q%5B0%5D=Vrindavan')
            ->assertOk()
            ->assertExactJson(['tours' => [], 'destinations' => [], 'places' => []]);
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
